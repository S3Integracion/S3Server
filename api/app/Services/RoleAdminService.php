<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Http\Request;
use App\Repositories\PermissionRepository;
use App\Repositories\RoleRepository;

final class RoleAdminService
{
    private RoleRepository $roleRepository;
    private PermissionRepository $permissionRepository;
    private AuditService $auditService;

    public function __construct(
        RoleRepository $roleRepository,
        PermissionRepository $permissionRepository,
        AuditService $auditService
    ) {
        $this->roleRepository = $roleRepository;
        $this->permissionRepository = $permissionRepository;
        $this->auditService = $auditService;
    }

    public function listRoles(int $page, int $perPage, string $search): array
    {
        $offset = ($page - 1) * $perPage;
        $items = $this->roleRepository->listRoles($perPage, $offset, $search);

        foreach ($items as &$item) {
            $item['id'] = (int) ($item['id'] ?? 0);
            $item['is_active'] = (int) ($item['is_active'] ?? 0) === 1;
            $item['permission_ids'] = $this->roleRepository->getPermissionIds((int) $item['id']);
        }

        return [
            'items' => $items,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $this->roleRepository->countRoles($search),
            ],
        ];
    }

    public function getRoleById(int $roleId): array
    {
        $role = $this->roleRepository->findById($roleId);
        if ($role === null) {
            throw new HttpException(404, 'role_not_found', 'Role not found.');
        }

        $role['id'] = (int) ($role['id'] ?? 0);
        $role['is_active'] = (int) ($role['is_active'] ?? 0) === 1;
        $role['permission_ids'] = $this->roleRepository->getPermissionIds($roleId);

        return $role;
    }

    public function createRole(array $payload, int $actorId, Request $request): array
    {
        if ($this->roleRepository->existsByName($payload['name'])) {
            throw new HttpException(409, 'role_conflict', 'Role name already exists.');
        }

        $roleId = $this->roleRepository->createRole([
            'name' => $payload['name'],
            'description' => $payload['description'],
            'is_active' => $payload['is_active'],
        ]);

        $this->auditService->record($actorId, 'admin.roles.created', 'roles', 'create', $request, [
            'target_role_id' => $roleId,
        ]);

        return $this->getRoleById($roleId);
    }

    public function updateRole(int $roleId, array $payload, int $actorId, Request $request): array
    {
        $current = $this->roleRepository->findById($roleId);
        if ($current === null) {
            throw new HttpException(404, 'role_not_found', 'Role not found.');
        }

        if (array_key_exists('name', $payload) && $this->roleRepository->existsByName($payload['name'], $roleId)) {
            throw new HttpException(409, 'role_conflict', 'Role name already exists.');
        }

        $fields = [];
        if (array_key_exists('name', $payload)) {
            $fields['name'] = $payload['name'];
        }
        if (array_key_exists('description', $payload)) {
            $fields['description'] = $payload['description'];
        }
        if (array_key_exists('is_active', $payload)) {
            $fields['is_active'] = $payload['is_active'];
        }

        $this->roleRepository->updateRole($roleId, $fields);

        $this->auditService->record($actorId, 'admin.roles.updated', 'roles', 'update', $request, [
            'target_role_id' => $roleId,
            'updated_fields' => array_keys($fields),
        ]);

        return $this->getRoleById($roleId);
    }

    /**
     * @param int[] $permissionIds
     */
    public function assignPermissions(int $roleId, array $permissionIds, int $actorId, Request $request): array
    {
        $current = $this->roleRepository->findById($roleId);
        if ($current === null) {
            throw new HttpException(404, 'role_not_found', 'Role not found.');
        }

        if (!empty($permissionIds)) {
            $existing = $this->permissionRepository->countActivePermissionsByIds($permissionIds);
            if ($existing !== count($permissionIds)) {
                throw new HttpException(422, 'invalid_permission_ids', 'One or more permission_ids are invalid or inactive.');
            }
        }

        $this->roleRepository->setPermissions($roleId, $permissionIds);

        $this->auditService->record($actorId, 'admin.roles.permissions.updated', 'roles', 'assign_permissions', $request, [
            'target_role_id' => $roleId,
            'permission_ids' => $permissionIds,
        ]);

        return $this->getRoleById($roleId);
    }
}
