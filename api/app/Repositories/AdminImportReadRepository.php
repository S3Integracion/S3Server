<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class AdminImportReadRepository
{
    public function __construct(private PDO $pdo) {}

    public function count(array $filters): int
    {
        [$where, $params] = $this->buildWhere($filters);
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM import_runs ir $where");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function list(int $limit, int $offset, array $filters): array
    {
        [$where, $params] = $this->buildWhere($filters);
        $sql = "SELECT ir.id, ir.run_uuid, ir.user_id, ir.marketplace_id,
                       ir.extension_version, ir.extraction_mode, ir.seller_host,
                       ir.extracted_at, ir.uploaded_at, ir.row_count,
                       ir.inserted_count, ir.updated_count, ir.rejected_count,
                       ir.status, ir.error_summary, ir.created_at, ir.updated_at,
                       am.country_code AS marketplace_country
                FROM import_runs ir
                LEFT JOIN amazon_marketplaces am ON am.id = ir.marketplace_id
                $where ORDER BY ir.id DESC LIMIT :limit OFFSET :offset";
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
            'SELECT ir.*, am.country_code AS marketplace_country
             FROM import_runs ir
             LEFT JOIN amazon_marketplaces am ON am.id = ir.marketplace_id
             WHERE ir.id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public function countFailures(int $importRunId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM import_failures WHERE import_run_id = :id');
        $stmt->execute([':id' => $importRunId]);
        return (int) $stmt->fetchColumn();
    }

    public function listFailures(int $importRunId, int $limit, int $offset): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, row_number, order_id, raw_data, error_code, error_message, created_at
             FROM import_failures WHERE import_run_id = :id
             ORDER BY row_number ASC LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':id', $importRunId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function buildWhere(array $filters): array
    {
        $clauses = [];
        $params  = [];

        if (!empty($filters['status'])) {
            $clauses[] = 'ir.status = :status';
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['user_id'])) {
            $clauses[] = 'ir.user_id = :user_id';
            $params[':user_id'] = (int) $filters['user_id'];
        }
        if (!empty($filters['date_from'])) {
            $clauses[] = 'ir.created_at >= :date_from';
            $params[':date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $clauses[] = 'ir.created_at <= :date_to';
            $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
        }

        $where = empty($clauses) ? '' : 'WHERE ' . implode(' AND ', $clauses);
        return [$where, $params];
    }
}
