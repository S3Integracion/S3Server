<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\PermissionService;
use App\Validation\Validator;

final class AdminPermissionsController
{
    private PermissionService $permissionService;

    public function __construct(PermissionService $permissionService)
    {
        $this->permissionService = $permissionService;
    }

    public function index(Request $request): void
    {
        $pagination = Validator::pagination($request->allQuery());
        $result = $this->permissionService->listPermissions(
            $pagination['page'],
            $pagination['per_page'],
            $pagination['search']
        );

        Response::success($result['items'], 'Permissions fetched.', 200, $result['meta']);
    }
}
