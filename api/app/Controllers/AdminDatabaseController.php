<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\AdminDatabaseService;
use App\Validation\Validator;

final class AdminDatabaseController
{
    public function __construct(private AdminDatabaseService $service) {}

    public function tables(Request $request): void
    {
        Response::success($this->service->listTables(), 'Tables fetched.');
    }

    public function browseTable(Request $request): void
    {
        $db    = Validator::requiredString(['db' => $request->routeParam('db')], 'db', 1, 64);
        $table = Validator::requiredString(['table' => $request->routeParam('table')], 'table', 1, 64);

        $query      = $request->allQuery();
        $pagination = Validator::pagination($query);

        $result = $this->service->browseTable(
            $db,
            $table,
            $pagination['page'],
            $pagination['per_page'],
            $pagination['search']
        );

        Response::success($result, 'Table data fetched.');
    }

    public function columns(Request $request): void
    {
        $db    = Validator::requiredString(['db' => $request->routeParam('db')], 'db', 1, 64);
        $table = Validator::requiredString(['table' => $request->routeParam('table')], 'table', 1, 64);

        $cols = $this->service->getColumns($db, $table);
        Response::success($cols, 'Columns fetched.');
    }
}
