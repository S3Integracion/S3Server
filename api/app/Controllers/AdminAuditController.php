<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\AdminAuditService;
use App\Validation\Validator;

final class AdminAuditController
{
    public function __construct(private AdminAuditService $service) {}

    public function index(Request $request): void
    {
        $query      = $request->allQuery();
        $pagination = Validator::pagination($query);

        $filters = [];
        if (!empty($query['search']))    $filters['search']    = trim((string) $query['search']);
        if (!empty($query['user_id']))   $filters['user_id']   = (int) $query['user_id'];
        if (!empty($query['event']))     $filters['event']     = trim((string) $query['event']);
        if (!empty($query['date_from'])) $filters['date_from'] = trim((string) $query['date_from']);
        if (!empty($query['date_to']))   $filters['date_to']   = trim((string) $query['date_to']);

        $result = $this->service->listAudit(
            $pagination['page'],
            $pagination['per_page'],
            $filters
        );

        Response::success($result['items'], 'Audit log fetched.', 200, $result['meta']);
    }

    public function show(Request $request): void
    {
        $id    = Validator::routeInt($request->routeParam('id'), 'id');
        $entry = $this->service->getEntry($id);
        Response::success($entry, 'Audit entry fetched.');
    }
}
