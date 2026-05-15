<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Env;
use PDO;

final class AuditRepository
{
    private PDO $pdo;
    private string $table;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->table = Env::get('AUDIT_LOGS_TABLE', 'audit_logs') ?? 'audit_logs';
    }

    public function record(
        ?int $userId,
        string $event,
        string $resource,
        string $action,
        string $ipAddress,
        string $userAgent,
        array $meta
    ): void {
        $sql = sprintf(
            'INSERT INTO %s
             (user_id, event, resource, action, ip_address, user_agent, meta_json, created_at)
             VALUES (:user_id, :event, :resource, :action, :ip_address, :user_agent, :meta_json, :created_at)',
            $this->table
        );

        $statement = $this->pdo->prepare($sql);
        if ($userId === null) {
            $statement->bindValue(':user_id', null, PDO::PARAM_NULL);
        } else {
            $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        }

        $statement->bindValue(':event', substr($event, 0, 80), PDO::PARAM_STR);
        $statement->bindValue(':resource', substr($resource, 0, 80), PDO::PARAM_STR);
        $statement->bindValue(':action', substr($action, 0, 80), PDO::PARAM_STR);
        $statement->bindValue(':ip_address', substr($ipAddress, 0, 45), PDO::PARAM_STR);
        $statement->bindValue(':user_agent', substr($userAgent, 0, 255), PDO::PARAM_STR);
        $statement->bindValue(':meta_json', json_encode($meta, JSON_UNESCAPED_SLASHES), PDO::PARAM_STR);
        $statement->bindValue(':created_at', date('Y-m-d H:i:s'), PDO::PARAM_STR);
        $statement->execute();
    }
}
