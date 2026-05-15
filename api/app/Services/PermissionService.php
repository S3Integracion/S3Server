<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\PermissionRepository;

final class PermissionService
{
    private PermissionRepository $permissionRepository;

    public function __construct(PermissionRepository $permissionRepository)
    {
        $this->permissionRepository = $permissionRepository;
    }

    public function listPermissions(int $page, int $perPage, string $search): array
    {
        $offset = ($page - 1) * $perPage;
        $items = $this->permissionRepository->listPermissions($perPage, $offset, $search);

        foreach ($items as &$item) {
            $item['id'] = (int) ($item['id'] ?? 0);
            $item['is_active'] = (int) ($item['is_active'] ?? 0) === 1;
        }

        return [
            'items' => $items,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $this->permissionRepository->countPermissions($search),
            ],
        ];
    }
}
