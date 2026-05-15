<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ImportRunRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function create(array $data): int
    {
        $sql = 'INSERT INTO import_runs (
                    run_uuid, user_id, marketplace_id, extension_version, extraction_mode,
                    seller_host, extracted_at, uploaded_at, row_count, payload_hash,
                    client_ip, user_agent, status
                ) VALUES (
                    :run_uuid, :user_id, :marketplace_id, :extension_version, :extraction_mode,
                    :seller_host, :extracted_at, NOW(), :row_count, :payload_hash,
                    :client_ip, :user_agent, :status
                )';

        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':run_uuid', (string) $data['run_uuid'], PDO::PARAM_STR);
        $statement->bindValue(':user_id', (int) $data['user_id'], PDO::PARAM_INT);

        if (isset($data['marketplace_id']) && $data['marketplace_id'] !== null) {
            $statement->bindValue(':marketplace_id', (int) $data['marketplace_id'], PDO::PARAM_INT);
        } else {
            $statement->bindValue(':marketplace_id', null, PDO::PARAM_NULL);
        }

        $this->bindNullableString($statement, ':extension_version', $data['extension_version'] ?? null, 20);
        $statement->bindValue(':extraction_mode', (string) ($data['extraction_mode'] ?? 'order_details'), PDO::PARAM_STR);
        $this->bindNullableString($statement, ':seller_host', $data['seller_host'] ?? null, 60);
        $this->bindNullableString($statement, ':extracted_at', $data['extracted_at'] ?? null, 19);
        $statement->bindValue(':row_count', (int) ($data['row_count'] ?? 0), PDO::PARAM_INT);
        $this->bindNullableString($statement, ':payload_hash', $data['payload_hash'] ?? null, 64);
        $this->bindNullableString($statement, ':client_ip', $data['client_ip'] ?? null, 45);
        $this->bindNullableString($statement, ':user_agent', $data['user_agent'] ?? null, 255);
        $statement->bindValue(':status', (string) ($data['status'] ?? 'processing'), PDO::PARAM_STR);

        $statement->execute();
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Adds counts to the run and marks it completed. Using += semantics so
     * chunked uploads that reuse a run_uuid accumulate correctly.
     */
    public function complete(int $id, int $insertedCount, int $updatedCount, int $rejectedCount): void
    {
        $sql = 'UPDATE import_runs
                SET status = :status,
                    inserted_count = inserted_count + :inserted_count,
                    updated_count = updated_count + :updated_count,
                    rejected_count = rejected_count + :rejected_count
                WHERE id = :id';

        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':status', 'completed', PDO::PARAM_STR);
        $statement->bindValue(':inserted_count', $insertedCount, PDO::PARAM_INT);
        $statement->bindValue(':updated_count', $updatedCount, PDO::PARAM_INT);
        $statement->bindValue(':rejected_count', $rejectedCount, PDO::PARAM_INT);
        $statement->bindValue(':id', $id, PDO::PARAM_INT);
        $statement->execute();
    }

    /**
     * Adds the chunk's row count to an existing run. Used when subsequent
     * chunks share the same run_uuid.
     */
    public function appendRowCount(int $id, int $extraRowCount): void
    {
        $sql = 'UPDATE import_runs SET row_count = row_count + :extra WHERE id = :id';
        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':extra', $extraRowCount, PDO::PARAM_INT);
        $statement->bindValue(':id', $id, PDO::PARAM_INT);
        $statement->execute();
    }

    public function fail(int $id, string $errorSummary): void
    {
        $sql = 'UPDATE import_runs
                SET status = :status, error_summary = :error_summary
                WHERE id = :id';

        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':status', 'failed', PDO::PARAM_STR);
        $statement->bindValue(':error_summary', substr($errorSummary, 0, 65535), PDO::PARAM_STR);
        $statement->bindValue(':id', $id, PDO::PARAM_INT);
        $statement->execute();
    }

    public function recordFailure(int $importRunId, ?int $rowNumber, ?string $orderId, string $errorCode, string $errorMessage, ?array $rawData = null): void
    {
        $sql = 'INSERT INTO import_failures (import_run_id, row_number, order_id, raw_data, error_code, error_message)
                VALUES (:run_id, :row_number, :order_id, :raw_data, :error_code, :error_message)';

        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':run_id', $importRunId, PDO::PARAM_INT);
        if ($rowNumber === null) {
            $statement->bindValue(':row_number', null, PDO::PARAM_NULL);
        } else {
            $statement->bindValue(':row_number', $rowNumber, PDO::PARAM_INT);
        }
        $this->bindNullableString($statement, ':order_id', $orderId, 19);
        if ($rawData === null) {
            $statement->bindValue(':raw_data', null, PDO::PARAM_NULL);
        } else {
            $encoded = json_encode($rawData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $statement->bindValue(':raw_data', $encoded === false ? null : $encoded, $encoded === false ? PDO::PARAM_NULL : PDO::PARAM_STR);
        }
        $statement->bindValue(':error_code', substr($errorCode, 0, 60), PDO::PARAM_STR);
        $statement->bindValue(':error_message', substr($errorMessage, 0, 65535), PDO::PARAM_STR);
        $statement->execute();
    }

    private function bindNullableString(\PDOStatement $statement, string $param, ?string $value, int $maxLength): void
    {
        if ($value === null || $value === '') {
            $statement->bindValue($param, null, PDO::PARAM_NULL);
            return;
        }
        $statement->bindValue($param, substr($value, 0, $maxLength), PDO::PARAM_STR);
    }
}
