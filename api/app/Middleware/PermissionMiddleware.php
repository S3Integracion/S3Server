<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\HttpException;
use App\Http\Request;
use App\Repositories\PermissionRepository;

final class PermissionMiddleware
{
    private PermissionRepository $permissionRepository;

    public function __construct(PermissionRepository $permissionRepository)
    {
        $this->permissionRepository = $permissionRepository;
    }

    public function handle(Request $request, string $requiredPermission): void
    {
        $userId = (int) ($request->attribute('auth.user_id', 0) ?? 0);
        if ($userId <= 0) {
            throw new HttpException(401, 'unauthorized', 'Authentication is required.');
        }

        $permissions = $this->permissionRepository->getPermissionNamesByUserId($userId);
        $request->setAttribute('auth.permissions', $permissions);

        if (!in_array($requiredPermission, $permissions, true)) {
            throw new HttpException(403, 'forbidden', 'You do not have permission to perform this action.');
        }
    }
}
