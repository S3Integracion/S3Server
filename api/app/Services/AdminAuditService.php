<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Repositories\AuditReadRepository;

final class AdminAuditService
{
    public function __construct(private AuditReadRepository $repo) {}

    public function listAudit(int $page, int $perPage, array $filters): array
    {
        $total  = $this->repo->count($filters);
        $offset = ($page - 1) * $perPage;
        $items  = $this->repo->list($perPage, $offset, $filters);

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

    public function getEntry(int $id): array
    {
        $entry = $this->repo->findById($id);
        if ($entry === null) {
            throw new HttpException(404, 'not_found', 'Audit entry not found.');
        }
        return $entry;
    }
}
