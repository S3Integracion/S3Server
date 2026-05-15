<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Env;
use PDO;

final class UserRepository
{
    private PDO $pdo;
    private string $usersTable;
    private string $userRolesTable;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->usersTable = Env::get('USERS_TABLE', 'users') ?? 'users';
        $this->userRolesTable = Env::get('USER_ROLES_TABLE', 'user_roles') ?? 'user_roles';
    }

    public function findByIdentifier(string $identifier): ?array
    {
        $sql = sprintf(
            'SELECT id, username, email, password_hash, is_active, created_at, updated_at, last_login_at
             FROM %s
             WHERE username = :identifier_username OR email = :identifier_email
             LIMIT 1',
            $this->usersTable
        );

        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':identifier_username', $identifier, PDO::PARAM_STR);
        $statement->bindValue(':identifier_email', $identifier, PDO::PARAM_STR);
        $statement->execute();

        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    public function findForAdminById(int $id): ?array
    {
        $sql = sprintf(
            'SELECT id, username, email, is_active, created_at, updated_at, last_login_at
             FROM %s
             WHERE id = :id
             LIMIT 1',
            $this->usersTable
        );

        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':id', $id, PDO::PARAM_INT);
        $statement->execute();

        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    public function countUsers(string $search = ''): int
    {
        $sql = sprintf('SELECT COUNT(*) AS total FROM %s', $this->usersTable);
        if ($search !== '') {
            $sql .= ' WHERE username LIKE :search OR email LIKE :search';
        }

        $statement = $this->pdo->prepare($sql);
        if ($search !== '') {
            $statement->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
        }
        $statement->execute();

        $row = $statement->fetch();
        return (int) ($row['total'] ?? 0);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listUsers(int $limit, int $offset, string $search = ''): array
    {
        $sql = sprintf(
            'SELECT id, username, email, is_active, created_at, updated_at, last_login_at
             FROM %s',
            $this->usersTable
        );

        if ($search !== '') {
            $sql .= ' WHERE username LIKE :search OR email LIKE :search';
        }

        $sql .= ' ORDER BY id DESC LIMIT :limit OFFSET :offset';

        $statement = $this->pdo->prepare($sql);
        if ($search !== '') {
            $statement->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
        }
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        $rows = $statement->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public function existsByUsernameOrEmail(string $username, string $email, ?int $excludeId = null): bool
    {
        $sql = sprintf(
            'SELECT id FROM %s WHERE (username = :username OR email = :email)',
            $this->usersTable
        );

        if ($excludeId !== null) {
            $sql .= ' AND id <> :exclude_id';
        }

        $sql .= ' LIMIT 1';

        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':username', $username, PDO::PARAM_STR);
        $statement->bindValue(':email', $email, PDO::PARAM_STR);
        if ($excludeId !== null) {
            $statement->bindValue(':exclude_id', $excludeId, PDO::PARAM_INT);
        }
        $statement->execute();

        return is_array($statement->fetch());
    }

    public function createUser(array $data): int
    {
        $sql = sprintf(
            'INSERT INTO %s (username, email, password_hash, is_active, created_at, updated_at)
             VALUES (:username, :email, :password_hash, :is_active, :created_at, :updated_at)',
            $this->usersTable
        );

        $now = date('Y-m-d H:i:s');
        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':username', $data['username'], PDO::PARAM_STR);
        $statement->bindValue(':email', $data['email'], PDO::PARAM_STR);
        $statement->bindValue(':password_hash', $data['password_hash'], PDO::PARAM_STR);
        $statement->bindValue(':is_active', $data['is_active'] ? 1 : 0, PDO::PARAM_INT);
        $statement->bindValue(':created_at', $now, PDO::PARAM_STR);
        $statement->bindValue(':updated_at', $now, PDO::PARAM_STR);
        $statement->execute();

        return (int) $this->pdo->lastInsertId();
    }

    public function updateUser(int $userId, array $fields): void
    {
        if (empty($fields)) {
            return;
        }

        $set = [];
        $allowed = ['username', 'email', 'password_hash', 'is_active'];
        $params = [':id' => $userId, ':updated_at' => date('Y-m-d H:i:s')];

        foreach ($allowed as $column) {
            if (!array_key_exists($column, $fields)) {
                continue;
            }
            $set[] = $column . ' = :' . $column;
            $params[':' . $column] = $fields[$column];
        }

        if (empty($set)) {
            return;
        }

        $set[] = 'updated_at = :updated_at';

        $sql = sprintf('UPDATE %s SET %s WHERE id = :id', $this->usersTable, implode(', ', $set));
        $statement = $this->pdo->prepare($sql);

        foreach ($params as $name => $value) {
            if ($name === ':id') {
                $statement->bindValue($name, (int) $value, PDO::PARAM_INT);
                continue;
            }

            if ($name === ':is_active') {
                $statement->bindValue($name, $value ? 1 : 0, PDO::PARAM_INT);
                continue;
            }

            $statement->bindValue($name, (string) $value, PDO::PARAM_STR);
        }

        $statement->execute();
    }

    public function touchLastLogin(int $userId): void
    {
        $sql = sprintf(
            'UPDATE %s SET last_login_at = :last_login_at, updated_at = :updated_at WHERE id = :id',
            $this->usersTable
        );

        $now = date('Y-m-d H:i:s');
        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':last_login_at', $now, PDO::PARAM_STR);
        $statement->bindValue(':updated_at', $now, PDO::PARAM_STR);
        $statement->bindValue(':id', $userId, PDO::PARAM_INT);
        $statement->execute();
    }

    /**
     * @return int[]
     */
    public function getRoleIds(int $userId): array
    {
        $sql = sprintf('SELECT role_id FROM %s WHERE user_id = :user_id', $this->userRolesTable);
        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->execute();

        $rows = $statement->fetchAll();
        if (!is_array($rows)) {
            return [];
        }

        $roleIds = [];
        foreach ($rows as $row) {
            $roleIds[] = (int) ($row['role_id'] ?? 0);
        }

        return array_values(array_filter($roleIds, static fn(int $id): bool => $id > 0));
    }

    /**
     * @param int[] $roleIds
     */
    public function setRoles(int $userId, array $roleIds): void
    {
        $this->pdo->beginTransaction();

        try {
            $deleteSql = sprintf('DELETE FROM %s WHERE user_id = :user_id', $this->userRolesTable);
            $deleteStatement = $this->pdo->prepare($deleteSql);
            $deleteStatement->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $deleteStatement->execute();

            if (!empty($roleIds)) {
                $insertSql = sprintf(
                    'INSERT INTO %s (user_id, role_id, created_at) VALUES (:user_id, :role_id, :created_at)',
                    $this->userRolesTable
                );
                $insertStatement = $this->pdo->prepare($insertSql);

                $createdAt = date('Y-m-d H:i:s');
                foreach ($roleIds as $roleId) {
                    $insertStatement->bindValue(':user_id', $userId, PDO::PARAM_INT);
                    $insertStatement->bindValue(':role_id', $roleId, PDO::PARAM_INT);
                    $insertStatement->bindValue(':created_at', $createdAt, PDO::PARAM_STR);
                    $insertStatement->execute();
                }
            }

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }
}
