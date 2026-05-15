<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Env;
use PDO;

final class LoginAttemptRepository
{
    private PDO $pdo;
    private string $table;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->table = Env::get('LOGIN_ATTEMPTS_TABLE', 'login_attempts') ?? 'login_attempts';
    }

    public function record(
        string $identifier,
        string $ipAddress,
        bool $successful,
        ?int $userId,
        string $reason
    ): void {
        $sql = sprintf(
            'INSERT INTO %s (identifier, ip_address, successful, attempted_at, user_id, reason)
             VALUES (:identifier, :ip_address, :successful, :attempted_at, :user_id, :reason)',
            $this->table
        );

        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':identifier', $identifier, PDO::PARAM_STR);
        $statement->bindValue(':ip_address', substr($ipAddress, 0, 45), PDO::PARAM_STR);
        $statement->bindValue(':successful', $successful ? 1 : 0, PDO::PARAM_INT);
        $statement->bindValue(':attempted_at', time(), PDO::PARAM_INT);
        if ($userId === null) {
            $statement->bindValue(':user_id', null, PDO::PARAM_NULL);
        } else {
            $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        }
        $statement->bindValue(':reason', substr($reason, 0, 120), PDO::PARAM_STR);
        $statement->execute();
    }

    /**
     * @return array{failures:int,last_failure:int}
     */
    public function getFailureStats(string $identifier, string $ipAddress, int $windowStart): array
    {
        $lastSuccessSql = sprintf(
            'SELECT COALESCE(MAX(attempted_at), 0) AS last_success
             FROM %s
             WHERE identifier = :identifier
               AND ip_address = :ip_address
               AND successful = 1',
            $this->table
        );

        $lastSuccessStatement = $this->pdo->prepare($lastSuccessSql);
        $lastSuccessStatement->bindValue(':identifier', $identifier, PDO::PARAM_STR);
        $lastSuccessStatement->bindValue(':ip_address', $ipAddress, PDO::PARAM_STR);
        $lastSuccessStatement->execute();

        $successRow = $lastSuccessStatement->fetch();
        $lastSuccess = (int) ($successRow['last_success'] ?? 0);
        $effectiveStart = max($windowStart, $lastSuccess + 1);

        $statsSql = sprintf(
            'SELECT COUNT(*) AS failures, COALESCE(MAX(attempted_at), 0) AS last_failure
             FROM %s
             WHERE identifier = :identifier
               AND ip_address = :ip_address
               AND successful = 0
               AND attempted_at >= :start_ts',
            $this->table
        );

        $statsStatement = $this->pdo->prepare($statsSql);
        $statsStatement->bindValue(':identifier', $identifier, PDO::PARAM_STR);
        $statsStatement->bindValue(':ip_address', $ipAddress, PDO::PARAM_STR);
        $statsStatement->bindValue(':start_ts', $effectiveStart, PDO::PARAM_INT);
        $statsStatement->execute();

        $row = $statsStatement->fetch();

        return [
            'failures' => (int) ($row['failures'] ?? 0),
            'last_failure' => (int) ($row['last_failure'] ?? 0),
        ];
    }
}
