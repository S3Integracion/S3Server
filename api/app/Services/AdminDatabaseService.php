<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\DatabaseBrowserRepository;

final class AdminDatabaseService
{
    public function __construct(private DatabaseBrowserRepository $repo) {}

    public function listTables(): array
    {
        $rows = $this->repo->listTables();

        $grouped = [];
        foreach ($rows as $row) {
            $db    = $row['db_name'];
            $grouped[$db][] = $row;
        }

        return $grouped;
    }

    public function browseTable(string $db, string $table, int $page, int $perPage, string $search): array
    {
        $total  = $this->repo->countTableRows($db, $table, $search);
        $offset = ($page - 1) * $perPage;
        $rows   = $this->repo->browseTable($db, $table, $perPage, $offset, $search);
        $cols   = $this->repo->getColumns($db, $table);

        return [
            'rows'    => $rows,
            'columns' => $cols,
            'meta'    => [
                'total'    => $total,
                'page'     => $page,
                'per_page' => $perPage,
                'pages'    => $perPage > 0 ? (int) ceil($total / $perPage) : 1,
            ],
        ];
    }

    public function getColumns(string $db, string $table): array
    {
        return $this->repo->getColumns($db, $table);
    }
}
