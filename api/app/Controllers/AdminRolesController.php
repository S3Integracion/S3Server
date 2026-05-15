<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Services\RoleAdminService;
use App\Validation\Validator;

final class AdminRolesController
{
    private RoleAdminService $roleAdminService;

    public function __construct(RoleAdminService $roleAdminService)
    {
        $this->roleAdminService = $roleAdminService;
    }

    public function index(Request $request): void
    {
        $pagination = Validator::pagination($request->allQuery());
        $result = $this->roleAdminService->listRoles(
            $pagination['page'],
            $pagination['per_page'],
            $pagination['search']
        );

        Response::success($result['items'], 'Roles fetched.', 200, $result['meta']);
    }

    public function create(Request $request): void
    {
        $payload = $request->json();

        $name = Validator::requiredString($payload, 'name', 2, 80);
        $description = Validator::optionalString($payload, 'description', 0, 255) ?? '';
        $isActive = Validator::optionalBool($payload, 'is_active') ?? true;

        $actorId = (int) ($request->attribute('auth.user_id', 0) ?? 0);

        $role = $this->roleAdminService->createRole([
            'name' => $name,
            'description' => $description,
            'is_active' => $isActive,
        ], $actorId, $request);

        Response::success($role, 'Role created.', 201);
    }

    public function show(Request $request): void
    {
        $roleId = Validator::routeInt($request->routeParam('id'), 'id');
        $role = $this->roleAdminService->getRoleById($roleId);

        Response::success($role, 'Role fetched.');
    }

    public function update(Request $request): void
    {
        $roleId = Validator::routeInt($request->routeParam('id'), 'id');
        $payload = $request->json();

        $updates = [];
        if (array_key_exists('name', $payload)) {
            $updates['name'] = Validator::requiredString($payload, 'name', 2, 80);
        }
        if (array_key_exists('description', $payload)) {
            $updates['description'] = Validator::optionalString($payload, 'description', 0, 255) ?? '';
        }
        if (array_key_exists('is_active', $payload)) {
            $updates['is_active'] = Validator::optionalBool($payload, 'is_active');
            if ($updates['is_active'] === null) {
                throw new HttpException(422, 'validation_error', 'Field "is_active" must be a boolean.');
            }
        }

        if (empty($updates)) {
            throw new HttpException(422, 'validation_error', 'At least one valid field is required to update role.');
        }

        $actorId = (int) ($request->attribute('auth.user_id', 0) ?? 0);

        $role = $this->roleAdminService->updateRole($roleId, $updates, $actorId, $request);

        Response::success($role, 'Role updated.');
    }

    public function assignPermissions(Request $request): void
    {
        $roleId = Validator::routeInt($request->routeParam('id'), 'id');
        $payload = $request->json();
        $permissionIds = Validator::requiredIntegerArray($payload, 'permission_ids', true);

        $actorId = (int) ($request->attribute('auth.user_id', 0) ?? 0);

        $role = $this->roleAdminService->assignPermissions($roleId, $permissionIds, $actorId, $request);

        Response::success($role, 'Role permissions updated.');
    }
}
