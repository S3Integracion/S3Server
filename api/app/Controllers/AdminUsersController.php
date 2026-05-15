<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\UserAdminService;
use App\Validation\Validator;

final class AdminUsersController
{
    private UserAdminService $userAdminService;

    public function __construct(UserAdminService $userAdminService)
    {
        $this->userAdminService = $userAdminService;
    }

    public function index(Request $request): void
    {
        $pagination = Validator::pagination($request->allQuery());
        $result = $this->userAdminService->listUsers(
            $pagination['page'],
            $pagination['per_page'],
            $pagination['search']
        );

        Response::success($result['items'], 'Users fetched.', 200, $result['meta']);
    }

    public function create(Request $request): void
    {
        $payload = $request->json();

        $username = Validator::requiredString($payload, 'username', 3, 60);
        $email = Validator::requiredEmail($payload, 'email');
        $password = Validator::requiredString($payload, 'password', 8, 255);
        $isActive = Validator::optionalBool($payload, 'is_active') ?? true;
        $roleIds = array_key_exists('role_ids', $payload)
            ? Validator::requiredIntegerArray($payload, 'role_ids', true)
            : [];

        $actorId = (int) ($request->attribute('auth.user_id', 0) ?? 0);

        $user = $this->userAdminService->createUser([
            'username' => $username,
            'email' => $email,
            'password' => $password,
            'is_active' => $isActive,
            'role_ids' => $roleIds,
        ], $actorId, $request);

        Response::success($user, 'User created.', 201);
    }

    public function show(Request $request): void
    {
        $userId = Validator::routeInt($request->routeParam('id'), 'id');
        $user = $this->userAdminService->getUserById($userId);

        Response::success($user, 'User fetched.');
    }

    public function update(Request $request): void
    {
        $userId = Validator::routeInt($request->routeParam('id'), 'id');
        $payload = $request->json();

        $updates = [];
        if (array_key_exists('username', $payload)) {
            $updates['username'] = Validator::requiredString($payload, 'username', 3, 60);
        }
        if (array_key_exists('email', $payload)) {
            $updates['email'] = Validator::requiredEmail($payload, 'email');
        }
        if (array_key_exists('password', $payload)) {
            $updates['password'] = Validator::requiredString($payload, 'password', 8, 255);
        }
        if (array_key_exists('is_active', $payload)) {
            $updates['is_active'] = Validator::optionalBool($payload, 'is_active');
            if ($updates['is_active'] === null) {
                throw new \App\Core\HttpException(422, 'validation_error', 'Field "is_active" must be a boolean.');
            }
        }

        if (empty($updates)) {
            throw new \App\Core\HttpException(422, 'validation_error', 'At least one valid field is required to update user.');
        }

        $actorId = (int) ($request->attribute('auth.user_id', 0) ?? 0);

        $user = $this->userAdminService->updateUser($userId, $updates, $actorId, $request);

        Response::success($user, 'User updated.');
    }

    public function assignRoles(Request $request): void
    {
        $userId = Validator::routeInt($request->routeParam('id'), 'id');
        $payload = $request->json();
        $roleIds = Validator::requiredIntegerArray($payload, 'role_ids', true);

        $actorId = (int) ($request->attribute('auth.user_id', 0) ?? 0);

        $user = $this->userAdminService->assignRoles($userId, $roleIds, $actorId, $request);

        Response::success($user, 'User roles updated.');
    }
}
