<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class AdminTokenReadRepository
{
    public function __construct(private PDO $pdo) {}

    public function count(array $filters): int
    {
        [$where, $params] = $this->buildWhere($filters);
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM api_tokens t $where");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function list(int $limit, int $offset, array $filters): array
    {
        [$where, $params] = $this->buildWhere($filters);
        // token_hash is NEVER selected
        $sql = "SELECT t.id, t.user_id, t.device_label,
                       t.created_at, t.expires_at, t.revoked_at,
                       t.last_used_at, t.last_used_ip, t.last_used_ua
                FROM api_tokens t $where
                ORDER BY t.id DESC LIMIT :limit OFFSET :offset";
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
            'SELECT id, user_id, device_label, created_at, expires_at,
                    revoked_at, last_used_at, last_used_ip, last_used_ua
             FROM api_tokens WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public function revokeById(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE api_tokens SET revoked_at = NOW() WHERE id = :id AND revoked_at IS NULL'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    private function buildWhere(array $filters): array
    {
        $clauses = [];
        $params  = [];

        if (!empty($filters['user_id'])) {
            $clauses[] = 't.user_id = :user_id';
            $params[':user_id'] = (int) $filters['user_id'];
        }
        if (!empty($filters['active_only'])) {
            $clauses[] = 't.revoked_at IS NULL AND t.expires_at > NOW()';
        }

        $where = empty($clauses) ? '' : 'WHERE ' . implode(' AND ', $clauses);
        return [$where, $params];
    }
}
