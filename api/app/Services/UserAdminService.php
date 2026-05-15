<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Http\Request;
use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;

final class UserAdminService
{
    private UserRepository $userRepository;
    private RoleRepository $roleRepository;
    private AuditService $auditService;

    public function __construct(
        UserRepository $userRepository,
        RoleRepository $roleRepository,
        AuditService $auditService
    ) {
        $this->userRepository = $userRepository;
        $this->roleRepository = $roleRepository;
        $this->auditService = $auditService;
    }

    public function listUsers(int $page, int $perPage, string $search): array
    {
        $offset = ($page - 1) * $perPage;
        $items = $this->userRepository->listUsers($perPage, $offset, $search);

        foreach ($items as &$item) {
            $item['id'] = (int) ($item['id'] ?? 0);
            $item['is_active'] = (int) ($item['is_active'] ?? 0) === 1;
            $item['role_ids'] = $this->userRepository->getRoleIds((int) $item['id']);
        }

        return [
            'items' => $items,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $this->userRepository->countUsers($search),
            ],
        ];
    }

    public function getUserById(int $userId): array
    {
        $user = $this->userRepository->findForAdminById($userId);
        if ($user === null) {
            throw new HttpException(404, 'user_not_found', 'User not found.');
        }

        $user['id'] = (int) ($user['id'] ?? 0);
        $user['is_active'] = (int) ($user['is_active'] ?? 0) === 1;
        $user['role_ids'] = $this->userRepository->getRoleIds($userId);

        return $user;
    }

    public function createUser(array $payload, int $actorId, Request $request): array
    {
        if ($this->userRepository->existsByUsernameOrEmail($payload['username'], $payload['email'])) {
            throw new HttpException(409, 'user_conflict', 'Username or email already exists.');
        }

        $passwordAlgo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
        $passwordHash = password_hash($payload['password'], $passwordAlgo);
        if (!is_string($passwordHash) || $passwordHash === '') {
            throw new HttpException(500, 'password_hash_error', 'Unable to hash password.');
        }

        $userId = $this->userRepository->createUser([
            'username' => $payload['username'],
            'email' => $payload['email'],
            'password_hash' => $passwordHash,
            'is_active' => $payload['is_active'],
        ]);

        if (!empty($payload['role_ids'])) {
            $this->assertRoleIdsExist($payload['role_ids']);
            $this->userRepository->setRoles($userId, $payload['role_ids']);
        }

        $this->auditService->record($actorId, 'admin.users.created', 'users', 'create', $request, [
            'target_user_id' => $userId,
        ]);

        return $this->getUserById($userId);
    }

    public function updateUser(int $userId, array $payload, int $actorId, Request $request): array
    {
        $current = $this->userRepository->findForAdminById($userId);
        if ($current === null) {
            throw new HttpException(404, 'user_not_found', 'User not found.');
        }

        $nextUsername = $payload['username'] ?? (string) $current['username'];
        $nextEmail = $payload['email'] ?? (string) $current['email'];

        if ($this->userRepository->existsByUsernameOrEmail($nextUsername, $nextEmail, $userId)) {
            throw new HttpException(409, 'user_conflict', 'Username or email already exists.');
        }

        $fields = [];
        if (array_key_exists('username', $payload)) {
            $fields['username'] = $payload['username'];
        }
        if (array_key_exists('email', $payload)) {
            $fields['email'] = $payload['email'];
        }
        if (array_key_exists('is_active', $payload)) {
            $fields['is_active'] = $payload['is_active'];
        }
        if (array_key_exists('password', $payload)) {
            $passwordAlgo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
            $passwordHash = password_hash($payload['password'], $passwordAlgo);
            if (!is_string($passwordHash) || $passwordHash === '') {
                throw new HttpException(500, 'password_hash_error', 'Unable to hash password.');
            }
            $fields['password_hash'] = $passwordHash;
        }

        $this->userRepository->updateUser($userId, $fields);

        $this->auditService->record($actorId, 'admin.users.updated', 'users', 'update', $request, [
            'target_user_id' => $userId,
            'updated_fields' => array_keys($fields),
        ]);

        return $this->getUserById($userId);
    }

    /**
     * @param int[] $roleIds
     */
    public function assignRoles(int $userId, array $roleIds, int $actorId, Request $request): array
    {
        $current = $this->userRepository->findForAdminById($userId);
        if ($current === null) {
            throw new HttpException(404, 'user_not_found', 'User not found.');
        }

        $this->assertRoleIdsExist($roleIds);
        $this->userRepository->setRoles($userId, $roleIds);

        $this->auditService->record($actorId, 'admin.users.roles.updated', 'users', 'assign_roles', $request, [
            'target_user_id' => $userId,
            'role_ids' => $roleIds,
        ]);

        return $this->getUserById($userId);
    }

    /**
     * @param int[] $roleIds
     */
    private function assertRoleIdsExist(array $roleIds): void
    {
        if (empty($roleIds)) {
            return;
        }

        $existing = $this->roleRepository->countActiveRolesByIds($roleIds);
        if ($existing !== count($roleIds)) {
            throw new HttpException(422, 'invalid_role_ids', 'One or more role_ids are invalid or inactive.');
        }
    }
}
