<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ApiTokenRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function create(int $userId, string $tokenHash, ?string $deviceLabel, int $ttlSeconds): array
    {
        $now = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', time() + max(60, $ttlSeconds));

        $sql = 'INSERT INTO api_tokens (user_id, token_hash, device_label, created_at, expires_at)
                VALUES (:user_id, :token_hash, :device_label, :created_at, :expires_at)';

        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->bindValue(':token_hash', $tokenHash, PDO::PARAM_STR);
        if ($deviceLabel === null || $deviceLabel === '') {
            $statement->bindValue(':device_label', null, PDO::PARAM_NULL);
        } else {
            $statement->bindValue(':device_label', $deviceLabel, PDO::PARAM_STR);
        }
        $statement->bindValue(':created_at', $now, PDO::PARAM_STR);
        $statement->bindValue(':expires_at', $expiresAt, PDO::PARAM_STR);
        $statement->execute();

        return [
            'id' => (int) $this->pdo->lastInsertId(),
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * Looks up a token row by its SHA-256 hash, requiring it to be active
     * (not revoked, not expired).
     */
    public function findValid(string $tokenHash): ?array
    {
        $sql = 'SELECT id, user_id, token_hash, device_label, created_at, expires_at, revoked_at, last_used_at
                FROM api_tokens
                WHERE token_hash = :token_hash
                  AND revoked_at IS NULL
                  AND expires_at > NOW()
                LIMIT 1';

        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':token_hash', $tokenHash, PDO::PARAM_STR);
        $statement->execute();

        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    public function revokeByHash(string $tokenHash): void
    {
        $sql = 'UPDATE api_tokens
                SET revoked_at = NOW()
                WHERE token_hash = :token_hash AND revoked_at IS NULL';

        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':token_hash', $tokenHash, PDO::PARAM_STR);
        $statement->execute();
    }

    public function touchLastUsed(int $tokenId, string $ipAddress, string $userAgent): void
    {
        $sql = 'UPDATE api_tokens
                SET last_used_at = NOW(),
                    last_used_ip = :ip,
                    last_used_ua = :ua
                WHERE id = :id';

        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':id', $tokenId, PDO::PARAM_INT);
        $statement->bindValue(':ip', substr($ipAddress, 0, 45), PDO::PARAM_STR);
        $statement->bindValue(':ua', substr($userAgent, 0, 255), PDO::PARAM_STR);
        $statement->execute();
    }
}
