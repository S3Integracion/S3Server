<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Env;
use PDO;

final class RoleRepository
{
    private PDO $pdo;
    private string $rolesTable;
    private string $userRolesTable;
    private string $rolePermissionsTable;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->rolesTable = Env::get('ROLES_TABLE', 'roles') ?? 'roles';
        $this->userRolesTable = Env::get('USER_ROLES_TABLE', 'user_roles') ?? 'user_roles';
        $this->rolePermissionsTable = Env::get('ROLE_PERMISSIONS_TABLE', 'role_permissions') ?? 'role_permissions';
    }

    public function countRoles(string $search = ''): int
    {
        $sql = sprintf('SELECT COUNT(*) AS total FROM %s', $this->rolesTable);
        if ($search !== '') {
            $sql .= ' WHERE name LIKE :search';
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
    public function listRoles(int $limit, int $offset, string $search = ''): array
    {
        $sql = sprintf(
            'SELECT id, name, description, is_active, created_at, updated_at
             FROM %s',
            $this->rolesTable
        );
        if ($search !== '') {
            $sql .= ' WHERE name LIKE :search';
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

    public function findById(int $roleId): ?array
    {
        $sql = sprintf(
            'SELECT id, name, description, is_active, created_at, updated_at
             FROM %s WHERE id = :id LIMIT 1',
            $this->rolesTable
        );

        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':id', $roleId, PDO::PARAM_INT);
        $statement->execute();

        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    public function existsByName(string $name, ?int $excludeId = null): bool
    {
        $sql = sprintf('SELECT id FROM %s WHERE name = :name', $this->rolesTable);
        if ($excludeId !== null) {
            $sql .= ' AND id <> :exclude_id';
        }
        $sql .= ' LIMIT 1';

        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':name', $name, PDO::PARAM_STR);
        if ($excludeId !== null) {
            $statement->bindValue(':exclude_id', $excludeId, PDO::PARAM_INT);
        }
        $statement->execute();

        return is_array($statement->fetch());
    }

    public function createRole(array $data): int
    {
        $sql = sprintf(
            'INSERT INTO %s (name, description, is_active, created_at, updated_at)
             VALUES (:name, :description, :is_active, :created_at, :updated_at)',
            $this->rolesTable
        );

        $now = date('Y-m-d H:i:s');
        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':name', $data['name'], PDO::PARAM_STR);
        $statement->bindValue(':description', $data['description'], PDO::PARAM_STR);
        $statement->bindValue(':is_active', $data['is_active'] ? 1 : 0, PDO::PARAM_INT);
        $statement->bindValue(':created_at', $now, PDO::PARAM_STR);
        $statement->bindValue(':updated_at', $now, PDO::PARAM_STR);
        $statement->execute();

        return (int) $this->pdo->lastInsertId();
    }

    public function updateRole(int $roleId, array $fields): void
    {
        if (empty($fields)) {
            return;
        }

        $set = [];
        $params = [':id' => $roleId, ':updated_at' => date('Y-m-d H:i:s')];
        $allowed = ['name', 'description', 'is_active'];

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

        $sql = sprintf('UPDATE %s SET %s WHERE id = :id', $this->rolesTable, implode(', ', $set));
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

    public function countActiveRolesByIds(array $roleIds): int
    {
        if (empty($roleIds)) {
            return 0;
        }

        $placeholders = implode(', ', array_fill(0, count($roleIds), '?'));
        $sql = sprintf('SELECT COUNT(*) AS total FROM %s WHERE id IN (%s) AND is_active = 1', $this->rolesTable, $placeholders);
        $statement = $this->pdo->prepare($sql);

        foreach (array_values($roleIds) as $index => $roleId) {
            $statement->bindValue($index + 1, $roleId, PDO::PARAM_INT);
        }

        $statement->execute();
        $row = $statement->fetch();
        return (int) ($row['total'] ?? 0);
    }

    /**
     * @return int[]
     */
    public function getPermissionIds(int $roleId): array
    {
        $sql = sprintf('SELECT permission_id FROM %s WHERE role_id = :role_id', $this->rolePermissionsTable);
        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':role_id', $roleId, PDO::PARAM_INT);
        $statement->execute();

        $rows = $statement->fetchAll();
        if (!is_array($rows)) {
            return [];
        }

        $permissionIds = [];
        foreach ($rows as $row) {
            $permissionIds[] = (int) ($row['permission_id'] ?? 0);
        }

        return array_values(array_filter($permissionIds, static fn(int $id): bool => $id > 0));
    }

    /**
     * @param int[] $permissionIds
     */
    public function setPermissions(int $roleId, array $permissionIds): void
    {
        $this->pdo->beginTransaction();

        try {
            $deleteSql = sprintf('DELETE FROM %s WHERE role_id = :role_id', $this->rolePermissionsTable);
            $deleteStatement = $this->pdo->prepare($deleteSql);
            $deleteStatement->bindValue(':role_id', $roleId, PDO::PARAM_INT);
            $deleteStatement->execute();

            if (!empty($permissionIds)) {
                $insertSql = sprintf(
                    'INSERT INTO %s (role_id, permission_id, created_at)
                     VALUES (:role_id, :permission_id, :created_at)',
                    $this->rolePermissionsTable
                );
                $insertStatement = $this->pdo->prepare($insertSql);

                $createdAt = date('Y-m-d H:i:s');
                foreach ($permissionIds as $permissionId) {
                    $insertStatement->bindValue(':role_id', $roleId, PDO::PARAM_INT);
                    $insertStatement->bindValue(':permission_id', $permissionId, PDO::PARAM_INT);
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

    /**
     * @return string[]
     */
    public function getRoleNamesByUserId(int $userId): array
    {
        $sql = sprintf(
            'SELECT r.name
             FROM %s r
             INNER JOIN %s ur ON ur.role_id = r.id
             WHERE ur.user_id = :user_id AND r.is_active = 1
             ORDER BY r.name ASC',
            $this->rolesTable,
            $this->userRolesTable
        );

        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->execute();

        $rows = $statement->fetchAll();
        if (!is_array($rows)) {
            return [];
        }

        $names = [];
        foreach ($rows as $row) {
            $names[] = (string) ($row['name'] ?? '');
        }

        return array_values(array_filter($names, static fn(string $value): bool => $value !== ''));
    }
}
