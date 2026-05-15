<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Core\Logger;
use App\Http\Request;
use App\Repositories\ImportRunRepository;
use App\Repositories\MarketplaceRepository;
use App\Repositories\OrderRepository;
use PDO;
use Throwable;

/**
 * Ingests Amazon order data from the Chrome extension into the OrderDetails
 * schema. One import_run per upload (chunks reuse the same run via run_uuid),
 * one transaction per order so a malformed order never aborts a whole run.
 *
 * The history triggers on orders/order_items/order_packages read MySQL session
 * vars @current_user_id and @current_run_id; we set them inside every order
 * transaction.
 */
final class OrderImportService
{
    private PDO $pdo;
    private ImportRunRepository $importRuns;
    private OrderRepository $orders;
    private MarketplaceRepository $marketplaces;
    private Logger $logger;

    public function __construct(
        PDO $pdo,
        ImportRunRepository $importRuns,
        OrderRepository $orders,
        MarketplaceRepository $marketplaces,
        Logger $logger
    ) {
        $this->pdo = $pdo;
        $this->importRuns = $importRuns;
        $this->orders = $orders;
        $this->marketplaces = $marketplaces;
        $this->logger = $logger;
    }

    /**
     * @param array<string,mixed> $payload  shape: {run: {...}, orders: [...]}
     */
    public function import(int $userId, array $payload, Request $request): array
    {
        $run = is_array($payload['run'] ?? null) ? $payload['run'] : [];
        $orders = is_array($payload['orders'] ?? null) ? $payload['orders'] : null;
        if ($orders === null) {
            throw new HttpException(422, 'validation_error', 'Field "orders" must be an array.');
        }
        if (count($orders) === 0) {
            throw new HttpException(422, 'validation_error', 'Field "orders" cannot be empty.');
        }
        if (count($orders) > 5000) {
            throw new HttpException(413, 'payload_too_large', 'A single import call cannot exceed 5000 orders.');
        }

        $runUuid = $this->validateRunUuid($run['run_uuid'] ?? null);
        $sellerHost = isset($run['seller_host']) ? strtolower(trim((string) $run['seller_host'])) : null;
        $marketplaceId = $this->marketplaces->findIdByHost($sellerHost);

        $importRunId = $this->resolveOrCreateRun($run, $userId, $runUuid, $sellerHost, $marketplaceId, count($orders), $request);

        $insertedTotal = 0;
        $updatedTotal = 0;
        $rejected = 0;
        $errorsSample = [];

        foreach ($orders as $index => $order) {
            $rowNumber = $index + 1;
            try {
                if (!is_array($order)) {
                    throw new \InvalidArgumentException('Order payload must be an object.');
                }
                $result = $this->ingestOrder($order, $importRunId, $userId, $marketplaceId);
                $insertedTotal += $result['inserted'];
                $updatedTotal += $result['updated'];
            } catch (Throwable $exception) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                $rejected++;
                $orderIdGuess = is_array($order) ? (string) ($order['order_id'] ?? '') : '';
                $errorCode = $exception instanceof \InvalidArgumentException ? 'validation_error' : 'database_error';
                $message = substr($exception->getMessage(), 0, 500);
                $this->importRuns->recordFailure(
                    $importRunId,
                    $rowNumber,
                    $orderIdGuess !== '' ? $orderIdGuess : null,
                    $errorCode,
                    $message,
                    is_array($order) ? $this->summarizeOrderForLog($order) : null
                );
                if (count($errorsSample) < 10) {
                    $errorsSample[] = [
                        'row' => $rowNumber,
                        'order_id' => $orderIdGuess,
                        'code' => $errorCode,
                        'message' => $message,
                    ];
                }
                $this->logger->warning('order_import_row_rejected', [
                    'import_run_id' => $importRunId,
                    'row' => $rowNumber,
                    'order_id' => $orderIdGuess,
                    'error' => $message,
                ]);
            }
        }

        $this->importRuns->complete($importRunId, $insertedTotal, $updatedTotal, $rejected);
        $this->writeAuditLog($importRunId, $userId, $request, [
            'run_uuid' => $runUuid,
            'seller_host' => $sellerHost,
            'inserted' => $insertedTotal,
            'updated' => $updatedTotal,
            'rejected' => $rejected,
            'orders_received' => count($orders),
        ]);

        return [
            'run_id' => $importRunId,
            'run_uuid' => $runUuid,
            'inserted' => $insertedTotal,
            'updated' => $updatedTotal,
            'rejected' => $rejected,
            'errors' => $errorsSample,
        ];
    }

    /**
     * @return array{inserted:int,updated:int}
     */
    private function ingestOrder(array $order, int $importRunId, int $userId, ?int $marketplaceId): array
    {
        $orderId = $this->validateOrderId($order['order_id'] ?? null);

        $this->pdo->beginTransaction();
        try {
            $this->orders->setSessionContext($userId, $importRunId);

            $orderResult = $this->orders->upsertOrder($order, $importRunId, $userId, $marketplaceId);
            $orderRefId = $orderResult['id'];
            $inserted = $orderResult['inserted'] ? 1 : 0;
            $updated = $orderResult['updated'] ? 1 : 0;
            $deliveryStatus = $this->orders->sanitizeDeliveryStatus($order['delivery_status'] ?? null);

            $itemRefByOrderItemId = [];
            $items = is_array($order['items'] ?? null) ? $order['items'] : [];
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $orderItemId = $this->validateOrderItemId($item['order_item_id'] ?? null);
                $itemResult = $this->orders->upsertOrderItem($orderRefId, $orderId, $item + ['order_item_id' => $orderItemId], $importRunId);
                $itemRefByOrderItemId[$orderItemId] = $itemResult['id'];
                $inserted += $itemResult['inserted'] ? 1 : 0;
                $updated += $itemResult['updated'] ? 1 : 0;
            }

            $packages = is_array($order['packages'] ?? null) ? $order['packages'] : [];
            foreach ($packages as $packageIdx => $package) {
                if (!is_array($package)) {
                    continue;
                }
                $packageNumber = isset($package['package_number'])
                    ? max(1, (int) $package['package_number'])
                    : ($packageIdx + 1);
                $package['package_number'] = $packageNumber;

                $packageResult = $this->orders->upsertOrderPackage($orderRefId, $orderId, $package, $importRunId);
                $packageId = $packageResult['id'];
                $inserted += $packageResult['inserted'] ? 1 : 0;
                $updated += $packageResult['updated'] ? 1 : 0;

                $packageItems = is_array($package['items'] ?? null) ? $package['items'] : [];
                foreach ($packageItems as $packageItem) {
                    if (!is_array($packageItem)) {
                        continue;
                    }
                    $piOrderItemId = $this->validateOrderItemId($packageItem['order_item_id'] ?? null);
                    $itemRefId = $itemRefByOrderItemId[$piOrderItemId] ?? null;
                    if ($itemRefId === null) {
                        // Package references an item not declared in items[]; skip silently
                        // rather than aborting the order — the schema requires the FK.
                        continue;
                    }
                    $this->orders->upsertPackageItem($packageId, $itemRefId, $piOrderItemId, $packageItem, $importRunId);
                }

                $events = is_array($package['events'] ?? null) ? $package['events'] : [];
                foreach ($events as $event) {
                    if (!is_array($event)) {
                        continue;
                    }
                    $wasInserted = $this->orders->insertDeliveryEvent(
                        $packageId,
                        $orderId,
                        isset($package['tracking_id']) ? (string) $package['tracking_id'] : null,
                        $event,
                        $importRunId
                    );
                    if ($wasInserted) {
                        $inserted++;
                    }
                }
            }

            $this->pdo->commit();

            // ── Post-import: update pending-order tables ────────────────────
            // Always remove from not-registered once the order is fully imported.
            $this->orders->deleteOrderNotRegistered($orderId);

            // Manage not-delivered based on delivery status.
            $finalStatuses = ['Entregado', 'No entregado', 'Cancelado'];
            if ($deliveryStatus !== null && in_array($deliveryStatus, $finalStatuses, true)) {
                $this->orders->deleteOrderNotDelivered($orderId);
            } elseif (in_array($deliveryStatus, ['Tránsito', 'Sin movimiento'], true)) {
                // Resolve first tracking ID from first package for this order.
                $firstTracking = null;
                $packages = is_array($order['packages'] ?? null) ? $order['packages'] : [];
                if (!empty($packages[0]['tracking_id'])) {
                    $firstTracking = (string) $packages[0]['tracking_id'];
                }
                $extractedAt = isset($order['purchase_datetime'])
                    ? (string) $order['purchase_datetime']
                    : date('Y-m-d H:i:s');
                $this->orders->upsertOrderNotDelivered($orderId, $firstTracking, $extractedAt);
            }
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }

        return ['inserted' => $inserted, 'updated' => $updated];
    }

    private function resolveOrCreateRun(
        array $run,
        int $userId,
        string $runUuid,
        ?string $sellerHost,
        ?int $marketplaceId,
        int $orderCount,
        Request $request
    ): int {
        // Allow chunked uploads to reuse a run id by client-supplied UUID.
        $existing = $this->findRunByUuid($runUuid);
        if ($existing !== null) {
            $this->importRuns->appendRowCount($existing, $orderCount);
            return $existing;
        }

        return $this->importRuns->create([
            'run_uuid' => $runUuid,
            'user_id' => $userId,
            'marketplace_id' => $marketplaceId,
            'extension_version' => isset($run['extension_version']) ? (string) $run['extension_version'] : null,
            'extraction_mode' => isset($run['extraction_mode']) ? (string) $run['extraction_mode'] : 'order_details',
            'seller_host' => $sellerHost,
            'extracted_at' => isset($run['extracted_at']) ? $this->normalizeDateTime((string) $run['extracted_at']) : null,
            'row_count' => $orderCount,
            'payload_hash' => isset($run['payload_hash']) ? substr((string) $run['payload_hash'], 0, 64) : null,
            'client_ip' => $request->ipAddress(),
            'user_agent' => $request->userAgent(),
            'status' => 'processing',
        ]);
    }

    private function findRunByUuid(string $runUuid): ?int
    {
        $statement = $this->pdo->prepare('SELECT id FROM import_runs WHERE run_uuid = :uuid LIMIT 1');
        $statement->bindValue(':uuid', $runUuid, PDO::PARAM_STR);
        $statement->execute();
        $row = $statement->fetch();
        return is_array($row) && isset($row['id']) ? (int) $row['id'] : null;
    }

    private function writeAuditLog(int $importRunId, int $userId, Request $request, array $eventData): void
    {
        $sql = 'INSERT INTO audit_log (event_type, user_id, import_run_id, event_data, client_ip, user_agent)
                VALUES (:event_type, :user_id, :import_run_id, :event_data, :client_ip, :user_agent)';
        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':event_type', 'order_details.import', PDO::PARAM_STR);
        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->bindValue(':import_run_id', $importRunId, PDO::PARAM_INT);
        $encoded = json_encode($eventData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $statement->bindValue(':event_data', $encoded === false ? null : $encoded, $encoded === false ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $statement->bindValue(':client_ip', $request->ipAddress(), PDO::PARAM_STR);
        $statement->bindValue(':user_agent', $request->userAgent(), PDO::PARAM_STR);
        $statement->execute();
    }

    private function validateRunUuid($value): string
    {
        if (is_string($value) && preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $value) === 1) {
            return strtolower($value);
        }
        // Fallback: server-generated UUID v4.
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return sprintf('%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }

    private function validateOrderId($value): string
    {
        if (!is_string($value)) {
            throw new \InvalidArgumentException('order_id must be a string.');
        }
        $trimmed = trim($value);
        if ($trimmed === '' || strlen($trimmed) > 19) {
            throw new \InvalidArgumentException('order_id must be between 1 and 19 characters.');
        }
        return $trimmed;
    }

    private function validateOrderItemId($value): string
    {
        if (!is_string($value) && !is_int($value)) {
            throw new \InvalidArgumentException('order_item_id must be a string or integer.');
        }
        $trimmed = trim((string) $value);
        if ($trimmed === '' || strlen($trimmed) > 25) {
            throw new \InvalidArgumentException('order_item_id must be between 1 and 25 characters.');
        }
        return $trimmed;
    }

    private function normalizeDateTime(string $value): ?string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }
        $iso = str_replace('T', ' ', $trimmed);
        return substr($iso, 0, 19);
    }

    private function summarizeOrderForLog(array $order): array
    {
        return [
            'order_id' => $order['order_id'] ?? null,
            'item_count' => is_array($order['items'] ?? null) ? count($order['items']) : 0,
            'package_count' => is_array($order['packages'] ?? null) ? count($order['packages']) : 0,
        ];
    }
}
