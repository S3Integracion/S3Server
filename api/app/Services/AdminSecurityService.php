<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\AdminSecurityRepository;

final class AdminSecurityService
{
    public function __construct(private AdminSecurityRepository $repo) {}

    public function listLoginAttempts(int $page, int $perPage, array $filters): array
    {
        $total  = $this->repo->countAttempts($filters);
        $offset = ($page - 1) * $perPage;
        $items  = $this->repo->listAttempts($perPage, $offset, $filters);

        return [
            'items' => $items,
            'meta'  => [
                'total'    => $total,
                'page'     => $page,
                'per_page' => $perPage,
                'pages'    => $perPage > 0 ? (int) ceil($total / $perPage) : 1,
            ],
        ];
    }

    public function getStats(): array
    {
        return $this->repo->getStats();
    }
}
