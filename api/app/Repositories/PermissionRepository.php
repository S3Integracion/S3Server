<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Env;
use PDO;

final class PermissionRepository
{
    private PDO $pdo;
    private string $permissionsTable;
    private string $rolesTable;
    private string $userRolesTable;
    private string $rolePermissionsTable;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->permissionsTable = Env::get('PERMISSIONS_TABLE', 'permissions') ?? 'permissions';
        $this->rolesTable = Env::get('ROLES_TABLE', 'roles') ?? 'roles';
        $this->userRolesTable = Env::get('USER_ROLES_TABLE', 'user_roles') ?? 'user_roles';
        $this->rolePermissionsTable = Env::get('ROLE_PERMISSIONS_TABLE', 'role_permissions') ?? 'role_permissions';
    }

    public function countPermissions(string $search = ''): int
    {
        $sql = sprintf('SELECT COUNT(*) AS total FROM %s', $this->permissionsTable);
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
    public function listPermissions(int $limit, int $offset, string $search = ''): array
    {
        $sql = sprintf(
            'SELECT id, name, description, is_active, created_at, updated_at
             FROM %s',
            $this->permissionsTable
        );
        if ($search !== '') {
            $sql .= ' WHERE name LIKE :search';
        }
        $sql .= ' ORDER BY id ASC LIMIT :limit OFFSET :offset';

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

    public function countActivePermissionsByIds(array $permissionIds): int
    {
        if (empty($permissionIds)) {
            return 0;
        }

        $placeholders = implode(', ', array_fill(0, count($permissionIds), '?'));
        $sql = sprintf(
            'SELECT COUNT(*) AS total FROM %s WHERE id IN (%s) AND is_active = 1',
            $this->permissionsTable,
            $placeholders
        );

        $statement = $this->pdo->prepare($sql);
        foreach (array_values($permissionIds) as $index => $permissionId) {
            $statement->bindValue($index + 1, $permissionId, PDO::PARAM_INT);
        }
        $statement->execute();

        $row = $statement->fetch();
        return (int) ($row['total'] ?? 0);
    }

    /**
     * @return string[]
     */
    public function getPermissionNamesByUserId(int $userId): array
    {
        $sql = sprintf(
            'SELECT DISTINCT p.name
             FROM %s p
             INNER JOIN %s rp ON rp.permission_id = p.id
             INNER JOIN %s ur ON ur.role_id = rp.role_id
             INNER JOIN %s r ON r.id = ur.role_id
             WHERE ur.user_id = :user_id
               AND p.is_active = 1
               AND r.is_active = 1
             ORDER BY p.name ASC',
            $this->permissionsTable,
            $this->rolePermissionsTable,
            $this->userRolesTable,
            $this->rolesTable
        );

        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->execute();

        $rows = $statement->fetchAll();
        if (!is_array($rows)) {
            return [];
        }

        $permissions = [];
        foreach ($rows as $row) {
            $permissions[] = (string) ($row['name'] ?? '');
        }

        return array_values(array_filter($permissions, static fn(string $name): bool => $name !== ''));
    }
}
