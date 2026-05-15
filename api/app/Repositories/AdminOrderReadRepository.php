<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class AdminOrderReadRepository
{
    public function __construct(private PDO $pdo) {}

    public function count(array $filters): int
    {
        [$where, $params] = $this->buildWhere($filters);
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM v_order_tracking_summary v $where"
        );
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function list(int $limit, int $offset, array $filters): array
    {
        [$where, $params] = $this->buildWhere($filters);
        $sql = "SELECT v.*, o.id AS orders_pk
                FROM v_order_tracking_summary v
                LEFT JOIN orders o ON o.order_id = v.order_id
                $where ORDER BY v.fecha_compra DESC LIMIT :limit OFFSET :offset";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT o.*, am.country_code AS marketplace_country, am.currency_code AS marketplace_currency,
                    am.host_pattern AS marketplace_host
             FROM orders o LEFT JOIN amazon_marketplaces am ON am.id = o.marketplace_id
             WHERE o.id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public function getOrderItems(int $orderId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM order_items WHERE order_id_ref = :id ORDER BY line_number ASC'
        );
        $stmt->execute([':id' => $orderId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getOrderPackages(int $orderId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM order_packages WHERE order_id_ref = :id ORDER BY package_number ASC'
        );
        $stmt->execute([':id' => $orderId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDeliveryEvents(int $packageId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, event_time, event_time_raw, location, event_details, created_at
             FROM delivery_events WHERE package_id = :pid ORDER BY event_time ASC'
        );
        $stmt->execute([':pid' => $packageId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getMarketplaces(): array
    {
        return $this->pdo
            ->query('SELECT id, host_pattern, country_code, currency_code FROM amazon_marketplaces WHERE is_active = 1 ORDER BY country_code')
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    private function buildWhere(array $filters): array
    {
        $clauses = [];
        $params  = [];

        if (!empty($filters['search'])) {
            $clauses[] = '(v.order_id LIKE :search OR v.product_name LIKE :search2 OR v.destinatario_nombre LIKE :search3)';
            $params[':search']  = '%' . $filters['search'] . '%';
            $params[':search2'] = '%' . $filters['search'] . '%';
            $params[':search3'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['marketplace_country'])) {
            $clauses[] = 'v.marketplace_country = :marketplace_country';
            $params[':marketplace_country'] = $filters['marketplace_country'];
        }
        if (!empty($filters['date_from'])) {
            $clauses[] = 'v.fecha_compra >= :date_from';
            $params[':date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $clauses[] = 'v.fecha_compra <= :date_to';
            $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
        }
        if (!empty($filters['delivery_status'])) {
            $clauses[] = 'v.delivery_status = :delivery_status';
            $params[':delivery_status'] = $filters['delivery_status'];
        }
        if (isset($filters['has_refund']) && $filters['has_refund'] !== '') {
            $clauses[] = 'v.has_refund = :has_refund';
            $params[':has_refund'] = $filters['has_refund'] === 'Sí' ? 'Sí' : 'No';
        }

        $where = empty($clauses) ? '' : 'WHERE ' . implode(' AND ', $clauses);
        return [$where, $params];
    }
}
