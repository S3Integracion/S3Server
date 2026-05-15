<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class AuditReadRepository
{
    public function __construct(private PDO $pdo) {}

    public function count(array $filters): int
    {
        [$where, $params] = $this->buildWhere($filters);
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id $where");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function list(int $limit, int $offset, array $filters): array
    {
        [$where, $params] = $this->buildWhere($filters);
        $sql = "SELECT a.id, a.user_id, u.username, a.event, a.resource, a.action,
                       a.ip_address, a.user_agent, a.meta_json, a.created_at
                FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id
                $where ORDER BY a.id DESC LIMIT :limit OFFSET :offset";
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
            'SELECT a.*, u.username FROM audit_logs a
             LEFT JOIN users u ON u.id = a.user_id WHERE a.id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    private function buildWhere(array $filters): array
    {
        $clauses = [];
        $params  = [];

        if (!empty($filters['search'])) {
            $clauses[] = '(a.event LIKE :search OR a.resource LIKE :search2 OR u.username LIKE :search3)';
            $params[':search']  = '%' . $filters['search'] . '%';
            $params[':search2'] = '%' . $filters['search'] . '%';
            $params[':search3'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['user_id'])) {
            $clauses[] = 'a.user_id = :user_id';
            $params[':user_id'] = (int) $filters['user_id'];
        }
        if (!empty($filters['event'])) {
            $clauses[] = 'a.event LIKE :event';
            $params[':event'] = '%' . $filters['event'] . '%';
        }
        if (!empty($filters['date_from'])) {
            $clauses[] = 'a.created_at >= :date_from';
            $params[':date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $clauses[] = 'a.created_at <= :date_to';
            $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
        }

        $where = empty($clauses) ? '' : 'WHERE ' . implode(' AND ', $clauses);
        return [$where, $params];
    }
}
