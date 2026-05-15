<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class OrderPendingRepository
{
    public function __construct(private PDO $pdo) {}

    // ---------------------------------------------------------------
    // order_not_registered
    // ---------------------------------------------------------------

    public function countNotRegistered(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM order_not_registered')->fetchColumn();
    }

    public function listNotRegistered(int $limit, int $offset): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, order_id, tracking_id, extracted_at, created_at
             FROM order_not_registered
             ORDER BY extracted_at DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Upserts a single not-registered entry.
     * Only inserts if order_id does NOT already exist in the orders table.
     */
    public function upsertNotRegisteredIfAbsent(string $orderId, ?string $trackingId, string $extractedAt): bool
    {
        $checkStmt = $this->pdo->prepare(
            'SELECT 1 FROM orders WHERE order_id = :order_id LIMIT 1'
        );
        $checkStmt->bindValue(':order_id', $orderId, PDO::PARAM_STR);
        $checkStmt->execute();
        if ($checkStmt->fetchColumn() !== false) {
            return false;
        }

        $sql = 'INSERT INTO order_not_registered (order_id, tracking_id, extracted_at)
                VALUES (:order_id, :tracking_id, :extracted_at)
                ON DUPLICATE KEY UPDATE
                    tracking_id = COALESCE(VALUES(tracking_id), tracking_id),
                    extracted_at = VALUES(extracted_at)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':order_id', $orderId, PDO::PARAM_STR);
        if ($trackingId !== null && $trackingId !== '') {
            $stmt->bindValue(':tracking_id', substr($trackingId, 0, 40), PDO::PARAM_STR);
        } else {
            $stmt->bindValue(':tracking_id', null, PDO::PARAM_NULL);
        }
        $stmt->bindValue(':extracted_at', $extractedAt, PDO::PARAM_STR);
        $stmt->execute();
        return true;
    }

    // ---------------------------------------------------------------
    // order_not_delivered
    // ---------------------------------------------------------------

    public function countNotDelivered(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM order_not_delivered')->fetchColumn();
    }

    public function listNotDelivered(int $limit, int $offset): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, order_id, tracking_id, extracted_at, created_at, updated_at
             FROM order_not_delivered
             ORDER BY extracted_at DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Upserts a not-delivered entry.
     * Only inserts if order_id EXISTS in the orders table AND delivery_status is non-final.
     */
    public function upsertNotDeliveredIfEligible(string $orderId, ?string $trackingId, string $extractedAt): bool
    {
        $checkStmt = $this->pdo->prepare(
            "SELECT 1 FROM orders
             WHERE order_id = :order_id
               AND (delivery_status IS NULL OR delivery_status IN ('Tránsito','Sin movimiento'))
             LIMIT 1"
        );
        $checkStmt->bindValue(':order_id', $orderId, PDO::PARAM_STR);
        $checkStmt->execute();
        if ($checkStmt->fetchColumn() === false) {
            return false;
        }

        $sql = 'INSERT INTO order_not_delivered (order_id, tracking_id, extracted_at)
                VALUES (:order_id, :tracking_id, :extracted_at)
                ON DUPLICATE KEY UPDATE
                    tracking_id = COALESCE(VALUES(tracking_id), tracking_id),
                    extracted_at = VALUES(extracted_at)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':order_id', $orderId, PDO::PARAM_STR);
        if ($trackingId !== null && $trackingId !== '') {
            $stmt->bindValue(':tracking_id', substr($trackingId, 0, 40), PDO::PARAM_STR);
        } else {
            $stmt->bindValue(':tracking_id', null, PDO::PARAM_NULL);
        }
        $stmt->bindValue(':extracted_at', $extractedAt, PDO::PARAM_STR);
        $stmt->execute();
        return true;
    }
}
