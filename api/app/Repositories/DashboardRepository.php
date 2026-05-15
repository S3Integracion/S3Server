<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class DashboardRepository
{
    public function __construct(private PDO $pdo, private PDO $ordersPdo) {}

    public function getStats(): array
    {
        $expiry = time() - (int) ($_ENV['SESSION_IDLE_TIMEOUT'] ?? 1800);

        $activeUsers = (int) $this->pdo
            ->query('SELECT COUNT(*) FROM users WHERE is_active = 1')
            ->fetchColumn();

        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM api_sessions WHERE revoked_at IS NULL AND last_activity > :expiry'
        );
        $stmt->execute([':expiry' => $expiry]);
        $activeSessions = (int) $stmt->fetchColumn();

        $totalImports = (int) $this->ordersPdo
            ->query('SELECT COUNT(*) FROM import_runs')
            ->fetchColumn();

        $failedImports = (int) $this->ordersPdo
            ->query("SELECT COUNT(*) FROM import_runs WHERE status = 'failed'")
            ->fetchColumn();

        $totalAudit = (int) $this->pdo
            ->query('SELECT COUNT(*) FROM audit_logs')
            ->fetchColumn();

        $totalOrders = (int) $this->ordersPdo
            ->query('SELECT COUNT(*) FROM orders')
            ->fetchColumn();

        return [
            'active_users'    => $activeUsers,
            'active_sessions' => $activeSessions,
            'total_imports'   => $totalImports,
            'failed_imports'  => $failedImports,
            'total_audit'     => $totalAudit,
            'total_orders'    => $totalOrders,
        ];
    }

    public function getRecentAudit(int $limit = 10): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.id, a.event, a.resource, a.action, a.ip_address, a.created_at, u.username
             FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id
             ORDER BY a.id DESC LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
