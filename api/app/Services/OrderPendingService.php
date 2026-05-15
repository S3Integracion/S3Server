<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Repositories\OrderPendingRepository;

final class OrderPendingService
{
    public function __construct(private OrderPendingRepository $repo) {}

    /**
     * Accepts a list of orders seen by the extension but not yet fully scraped.
     * Only inserts those whose order_id does NOT exist in the orders table.
     *
     * @param array{orders: array<array{order_id:string,tracking_id?:string,extracted_at:string}>} $payload
     */
    public function submitNotRegistered(array $payload): array
    {
        $rows = $this->validateRows($payload);

        $inserted = 0;
        $skipped = 0;
        foreach ($rows as $row) {
            $orderId = $this->sanitizeOrderId($row['order_id'] ?? null);
            if ($orderId === null) {
                $skipped++;
                continue;
            }
            $trackingId = isset($row['tracking_id']) ? substr(trim((string) $row['tracking_id']), 0, 40) : null;
            $extractedAt = $this->sanitizeDateTime($row['extracted_at'] ?? null) ?? date('Y-m-d H:i:s');

            $this->repo->upsertNotRegisteredIfAbsent($orderId, $trackingId ?: null, $extractedAt)
                ? $inserted++
                : $skipped++;
        }

        return ['inserted' => $inserted, 'skipped' => $skipped, 'total' => count($rows)];
    }

    /**
     * Accepts a list of registered orders whose delivery is still in-progress.
     * Only inserts those whose order_id EXISTS in orders with a non-final status.
     *
     * @param array{orders: array<array{order_id:string,tracking_id?:string,extracted_at:string}>} $payload
     */
    public function submitNotDelivered(array $payload): array
    {
        $rows = $this->validateRows($payload);

        $inserted = 0;
        $skipped = 0;
        foreach ($rows as $row) {
            $orderId = $this->sanitizeOrderId($row['order_id'] ?? null);
            if ($orderId === null) {
                $skipped++;
                continue;
            }
            $trackingId = isset($row['tracking_id']) ? substr(trim((string) $row['tracking_id']), 0, 40) : null;
            $extractedAt = $this->sanitizeDateTime($row['extracted_at'] ?? null) ?? date('Y-m-d H:i:s');

            $this->repo->upsertNotDeliveredIfEligible($orderId, $trackingId ?: null, $extractedAt)
                ? $inserted++
                : $skipped++;
        }

        return ['inserted' => $inserted, 'skipped' => $skipped, 'total' => count($rows)];
    }

    public function listNotRegistered(int $page, int $perPage): array
    {
        $perPage = min(max(1, $perPage), 500);
        $offset = max(0, ($page - 1) * $perPage);
        $total = $this->repo->countNotRegistered();
        $items = $this->repo->listNotRegistered($perPage, $offset);
        return [
            'data' => $items,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ];
    }

    public function listNotDelivered(int $page, int $perPage): array
    {
        $perPage = min(max(1, $perPage), 500);
        $offset = max(0, ($page - 1) * $perPage);
        $total = $this->repo->countNotDelivered();
        $items = $this->repo->listNotDelivered($perPage, $offset);
        return [
            'data' => $items,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ];
    }

    private function validateRows(array $payload): array
    {
        $rows = $payload['orders'] ?? null;
        if (!is_array($rows)) {
            throw new HttpException(422, 'validation_error', 'Field "orders" must be an array.');
        }
        if (count($rows) === 0) {
            throw new HttpException(422, 'validation_error', 'Field "orders" cannot be empty.');
        }
        if (count($rows) > 5000) {
            throw new HttpException(413, 'payload_too_large', 'A single call cannot exceed 5000 entries.');
        }
        return $rows;
    }

    private function sanitizeOrderId(mixed $value): ?string
    {
        if (!is_string($value) && !is_int($value)) {
            return null;
        }
        $trimmed = trim((string) $value);
        return ($trimmed !== '' && strlen($trimmed) <= 19) ? $trimmed : null;
    }

    private function sanitizeDateTime(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $iso = str_replace('T', ' ', trim($value));
        return substr($iso, 0, 19);
    }
}
