<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\HttpException;
use PDO;

final class DatabaseBrowserRepository
{
    private const ALLOWED_DATABASES = ['S3INTEGRACION', 'OrderDetails'];

    public function __construct(private PDO $pdo, private PDO $ordersPdo) {}

    public function listTables(): array
    {
        $dbs = implode(',', array_map(fn($d) => "'$d'", self::ALLOWED_DATABASES));
        $stmt = $this->pdo->prepare(
            "SELECT TABLE_SCHEMA AS db_name, TABLE_NAME AS table_name,
                    TABLE_TYPE AS table_type, ENGINE AS engine,
                    TABLE_ROWS AS approx_rows, DATA_LENGTH AS data_bytes,
                    CREATE_TIME AS created_at
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA IN ($dbs)
             ORDER BY TABLE_SCHEMA, TABLE_TYPE DESC, TABLE_NAME"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getColumns(string $dbName, string $tableName): array
    {
        $this->allowedDatabaseName($dbName);
        $this->allowedTableName($dbName, $tableName);

        $stmt = $this->pdo->prepare(
            "SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT,
                    COLUMN_KEY, EXTRA, COLUMN_COMMENT, ORDINAL_POSITION
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :table
             ORDER BY ORDINAL_POSITION"
        );
        $stmt->execute([':db' => $dbName, ':table' => $tableName]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function browseTable(string $dbName, string $tableName, int $limit, int $offset, string $search): array
    {
        $this->allowedDatabaseName($dbName);
        $this->allowedTableName($dbName, $tableName);

        $pdo = $dbName === 'OrderDetails' ? $this->ordersPdo : $this->pdo;
        $quotedTable = "`$dbName`.`$tableName`";

        if ($search !== '') {
            $firstVarchar = $this->getFirstVarcharColumn($dbName, $tableName);
            if ($firstVarchar !== null) {
                $stmt = $pdo->prepare(
                    "SELECT * FROM $quotedTable WHERE `$firstVarchar` LIKE :search
                     ORDER BY 1 DESC LIMIT :limit OFFSET :offset"
                );
                $stmt->bindValue(':search', '%' . $search . '%');
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                $stmt->execute();
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }

        $stmt = $pdo->prepare("SELECT * FROM $quotedTable ORDER BY 1 DESC LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countTableRows(string $dbName, string $tableName, string $search): int
    {
        $this->allowedDatabaseName($dbName);
        $this->allowedTableName($dbName, $tableName);

        $pdo = $dbName === 'OrderDetails' ? $this->ordersPdo : $this->pdo;
        $quotedTable = "`$dbName`.`$tableName`";

        if ($search !== '') {
            $firstVarchar = $this->getFirstVarcharColumn($dbName, $tableName);
            if ($firstVarchar !== null) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM $quotedTable WHERE `$firstVarchar` LIKE :search");
                $stmt->execute([':search' => '%' . $search . '%']);
                return (int) $stmt->fetchColumn();
            }
        }

        return (int) $pdo->query("SELECT COUNT(*) FROM $quotedTable")->fetchColumn();
    }

    private function getFirstVarcharColumn(string $dbName, string $tableName): ?string
    {
        $stmt = $this->pdo->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :table
               AND DATA_TYPE IN ('varchar','char','text','tinytext','mediumtext','longtext')
             ORDER BY ORDINAL_POSITION LIMIT 1"
        );
        $stmt->execute([':db' => $dbName, ':table' => $tableName]);
        $col = $stmt->fetchColumn();
        return $col !== false ? (string) $col : null;
    }

    private function allowedDatabaseName(string $db): void
    {
        if (!in_array($db, self::ALLOWED_DATABASES, true)) {
            throw new HttpException(400, 'invalid_database', "Database '$db' is not accessible.");
        }
    }

    private function allowedTableName(string $dbName, string $tableName): void
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :table"
        );
        $stmt->execute([':db' => $dbName, ':table' => $tableName]);
        if ((int) $stmt->fetchColumn() === 0) {
            throw new HttpException(400, 'invalid_table', "Table '$tableName' not found in database '$dbName'.");
        }
    }
}
