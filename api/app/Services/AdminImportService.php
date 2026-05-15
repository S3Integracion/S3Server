<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Repositories\AdminImportReadRepository;

final class AdminImportService
{
    public function __construct(private AdminImportReadRepository $repo) {}

    public function listImports(int $page, int $perPage, array $filters): array
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

    public function getImport(int $id): array
    {
        $run = $this->repo->findById($id);
        if ($run === null) {
            throw new HttpException(404, 'not_found', 'Import run not found.');
        }
        $run['failure_count'] = $this->repo->countFailures($id);
        return $run;
    }

    public function listFailures(int $importRunId, int $page, int $perPage): array
    {
        $run = $this->repo->findById($importRunId);
        if ($run === null) {
            throw new HttpException(404, 'not_found', 'Import run not found.');
        }

        $total  = $this->repo->countFailures($importRunId);
        $offset = ($page - 1) * $perPage;
        $items  = $this->repo->listFailures($importRunId, $perPage, $offset);

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
}
