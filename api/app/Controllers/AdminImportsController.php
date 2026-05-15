<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\AdminImportService;
use App\Validation\Validator;

final class AdminImportsController
{
    public function __construct(private AdminImportService $service) {}

    public function index(Request $request): void
    {
        $query      = $request->allQuery();
        $pagination = Validator::pagination($query);

        $filters = [];
        if (!empty($query['status']))    $filters['status']    = trim((string) $query['status']);
        if (!empty($query['user_id']))   $filters['user_id']   = (int) $query['user_id'];
        if (!empty($query['date_from'])) $filters['date_from'] = trim((string) $query['date_from']);
        if (!empty($query['date_to']))   $filters['date_to']   = trim((string) $query['date_to']);

        $result = $this->service->listImports($pagination['page'], $pagination['per_page'], $filters);
        Response::success($result['items'], 'Import runs fetched.', 200, $result['meta']);
    }

    public function show(Request $request): void
    {
        $id  = Validator::routeInt($request->routeParam('id'), 'id');
        $run = $this->service->getImport($id);
        Response::success($run, 'Import run fetched.');
    }

    public function failures(Request $request): void
    {
        $id         = Validator::routeInt($request->routeParam('id'), 'id');
        $query      = $request->allQuery();
        $pagination = Validator::pagination($query);

        $result = $this->service->listFailures($id, $pagination['page'], $pagination['per_page']);
        Response::success($result['items'], 'Import failures fetched.', 200, $result['meta']);
    }
}
