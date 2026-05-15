<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class AdminSecurityRepository
{
    public function __construct(private PDO $pdo) {}

    public function countAttempts(array $filters): int
    {
        [$where, $params] = $this->buildWhere($filters);
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM login_attempts la $where");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function listAttempts(int $limit, int $offset, array $filters): array
    {
        [$where, $params] = $this->buildWhere($filters);
        $sql = "SELECT la.id, la.identifier, la.ip_address, la.successful,
                       la.user_id, la.reason,
                       FROM_UNIXTIME(la.attempted_at) AS attempted_at_human,
                       la.attempted_at,
                       u.username
                FROM login_attempts la
                LEFT JOIN users u ON u.id = la.user_id
                $where ORDER BY la.attempted_at DESC LIMIT :limit OFFSET :offset";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getStats(): array
    {
        $window = time() - 86400; // last 24h

        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) AS total,
                    SUM(successful = 0) AS failed,
                    SUM(successful = 1) AS succeeded,
                    COUNT(DISTINCT ip_address) AS unique_ips
             FROM login_attempts WHERE attempted_at > :window'
        );
        $stmt->execute([':window' => $window]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        $locked = $this->getLockedIdentifiers();

        return [
            'last_24h' => [
                'total'      => (int) ($stats['total'] ?? 0),
                'failed'     => (int) ($stats['failed'] ?? 0),
                'succeeded'  => (int) ($stats['succeeded'] ?? 0),
                'unique_ips' => (int) ($stats['unique_ips'] ?? 0),
            ],
            'locked_identifiers' => $locked,
        ];
    }

    public function getLockedIdentifiers(): array
    {
        $maxAttempts = (int) ($_ENV['LOGIN_MAX_ATTEMPTS'] ?? 5);
        $windowSecs  = (int) ($_ENV['LOGIN_ATTEMPT_WINDOW_SECONDS'] ?? 900);
        $windowStart = time() - $windowSecs;

        $stmt = $this->pdo->prepare(
            'SELECT identifier, ip_address, COUNT(*) AS failures,
                    FROM_UNIXTIME(MAX(attempted_at)) AS last_attempt
             FROM login_attempts
             WHERE successful = 0 AND attempted_at > :window_start
             GROUP BY identifier, ip_address
             HAVING failures >= :max
             ORDER BY failures DESC'
        );
        $stmt->execute([':window_start' => $windowStart, ':max' => $maxAttempts]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function buildWhere(array $filters): array
    {
        $clauses = [];
        $params  = [];

        if (isset($filters['identifier']) && $filters['identifier'] !== '') {
            $clauses[] = 'la.identifier LIKE :identifier';
            $params[':identifier'] = '%' . $filters['identifier'] . '%';
        }
        if (isset($filters['ip_address']) && $filters['ip_address'] !== '') {
            $clauses[] = 'la.ip_address LIKE :ip_address';
            $params[':ip_address'] = '%' . $filters['ip_address'] . '%';
        }
        if (isset($filters['successful']) && $filters['successful'] !== '') {
            $clauses[] = 'la.successful = :successful';
            $params[':successful'] = (int) $filters['successful'];
        }
        if (!empty($filters['date_from'])) {
            $clauses[] = 'la.attempted_at >= UNIX_TIMESTAMP(:date_from)';
            $params[':date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $clauses[] = 'la.attempted_at <= UNIX_TIMESTAMP(:date_to)';
            $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
        }

        $where = empty($clauses) ? '' : 'WHERE ' . implode(' AND ', $clauses);
        return [$where, $params];
    }
}
