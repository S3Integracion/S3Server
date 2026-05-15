<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class SessionRepository
{
    public function __construct(private PDO $pdo) {}

    public function countSessions(string $search, bool $activeOnly): int
    {
        $expiry = time() - (int) ($_ENV['SESSION_IDLE_TIMEOUT'] ?? 1800);
        $sql = 'SELECT COUNT(*) FROM api_sessions s LEFT JOIN users u ON u.id = s.user_id WHERE 1=1';
        $params = [];
        if ($activeOnly) {
            $sql .= ' AND s.revoked_at IS NULL AND s.last_activity > :expiry';
            $params[':expiry'] = $expiry;
        }
        if ($search !== '') {
            $sql .= ' AND (s.ip_address LIKE :search OR u.username LIKE :search2)';
            $params[':search']  = '%' . $search . '%';
            $params[':search2'] = '%' . $search . '%';
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function listSessions(int $limit, int $offset, string $search, bool $activeOnly): array
    {
        $expiry = time() - (int) ($_ENV['SESSION_IDLE_TIMEOUT'] ?? 1800);
        $sql = 'SELECT s.id, s.user_id, u.username, s.ip_address, s.user_agent,
                       s.last_activity, s.created_at, s.revoked_at, s.updated_at
                FROM api_sessions s LEFT JOIN users u ON u.id = s.user_id
                WHERE 1=1';
        $params = [];
        if ($activeOnly) {
            $sql .= ' AND s.revoked_at IS NULL AND s.last_activity > :expiry';
            $params[':expiry'] = $expiry;
        }
        if ($search !== '') {
            $sql .= ' AND (s.ip_address LIKE :search OR u.username LIKE :search2)';
            $params[':search']  = '%' . $search . '%';
            $params[':search2'] = '%' . $search . '%';
        }
        $sql .= ' ORDER BY s.last_activity DESC LIMIT :limit OFFSET :offset';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(string $sessionId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.*, u.username FROM api_sessions s
             LEFT JOIN users u ON u.id = s.user_id
             WHERE s.id = :id'
        );
        $stmt->execute([':id' => $sessionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public function revokeById(string $sessionId): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE api_sessions SET revoked_at = NOW() WHERE id = :id AND revoked_at IS NULL'
        );
        $stmt->execute([':id' => $sessionId]);
        return $stmt->rowCount() > 0;
    }

    public function revokeAll(): int
    {
        $stmt = $this->pdo->prepare('UPDATE api_sessions SET revoked_at = NOW() WHERE revoked_at IS NULL');
        $stmt->execute();
        return $stmt->rowCount();
    }
}
