<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use SessionHandlerInterface;

final class DatabaseSessionHandler implements SessionHandlerInterface
{
    private PDO $pdo;
    private int $ttl;
    private string $table;

    public function __construct(PDO $pdo, int $ttl)
    {
        $this->pdo = $pdo;
        $this->ttl = $ttl;
        $this->table = Env::get('SESSION_TABLE', 'api_sessions') ?? 'api_sessions';
    }

    public function open(string $savePath, string $sessionName): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string
    {
        $sql = sprintf(
            'SELECT payload, last_activity, revoked_at FROM %s WHERE id = :id LIMIT 1',
            $this->table
        );
        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':id', $id, PDO::PARAM_STR);
        $statement->execute();

        $row = $statement->fetch();
        if (!is_array($row)) {
            return '';
        }

        if ($row['revoked_at'] !== null) {
            return '';
        }

        $lastActivity = (int) ($row['last_activity'] ?? 0);
        if (($lastActivity + $this->ttl) < time()) {
            return '';
        }

        return (string) ($row['payload'] ?? '');
    }

    public function write(string $id, string $data): bool
    {
        $timestamp = time();
        $now = date('Y-m-d H:i:s');

        $sql = sprintf(
            'INSERT INTO %s (id, payload, last_activity, created_at, updated_at, revoked_at)
             VALUES (:id, :payload, :last_activity, :created_at, :updated_at, NULL)
             ON DUPLICATE KEY UPDATE
                payload = VALUES(payload),
                last_activity = VALUES(last_activity),
                updated_at = VALUES(updated_at),
                revoked_at = NULL',
            $this->table
        );

        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':id', $id, PDO::PARAM_STR);
        $statement->bindValue(':payload', $data, PDO::PARAM_STR);
        $statement->bindValue(':last_activity', $timestamp, PDO::PARAM_INT);
        $statement->bindValue(':created_at', $now, PDO::PARAM_STR);
        $statement->bindValue(':updated_at', $now, PDO::PARAM_STR);

        return $statement->execute();
    }

    public function destroy(string $id): bool
    {
        $sql = sprintf('DELETE FROM %s WHERE id = :id', $this->table);
        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':id', $id, PDO::PARAM_STR);
        $statement->execute();
        return true;
    }

    public function gc(int $maxLifetime): int|false
    {
        $expiry = time() - max($maxLifetime, $this->ttl);
        $sql = sprintf('DELETE FROM %s WHERE last_activity < :expiry OR revoked_at IS NOT NULL', $this->table);
        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':expiry', $expiry, PDO::PARAM_INT);
        $statement->execute();

        return $statement->rowCount();
    }

    public function syncMetadata(string $sessionId, ?int $userId, string $ipAddress, string $userAgent): void
    {
        $sql = sprintf(
            'UPDATE %s
             SET user_id = :user_id,
                 ip_address = :ip_address,
                 user_agent = :user_agent,
                 updated_at = :updated_at
             WHERE id = :id',
            $this->table
        );

        $statement = $this->pdo->prepare($sql);
        if ($userId === null) {
            $statement->bindValue(':user_id', null, PDO::PARAM_NULL);
        } else {
            $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        }
        $statement->bindValue(':ip_address', substr($ipAddress, 0, 45), PDO::PARAM_STR);
        $statement->bindValue(':user_agent', substr($userAgent, 0, 255), PDO::PARAM_STR);
        $statement->bindValue(':updated_at', date('Y-m-d H:i:s'), PDO::PARAM_STR);
        $statement->bindValue(':id', $sessionId, PDO::PARAM_STR);
        $statement->execute();
    }

    public function revoke(string $sessionId): void
    {
        $sql = sprintf('UPDATE %s SET revoked_at = :revoked_at WHERE id = :id', $this->table);
        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':revoked_at', date('Y-m-d H:i:s'), PDO::PARAM_STR);
        $statement->bindValue(':id', $sessionId, PDO::PARAM_STR);
        $statement->execute();
    }
}
