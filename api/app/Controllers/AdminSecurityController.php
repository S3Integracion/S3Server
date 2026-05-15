<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\AdminSecurityService;
use App\Validation\Validator;

final class AdminSecurityController
{
    public function __construct(private AdminSecurityService $service) {}

    public function loginAttempts(Request $request): void
    {
        $query      = $request->allQuery();
        $pagination = Validator::pagination($query);

        $filters = [];
        if (isset($query['identifier']))  $filters['identifier']  = trim((string) $query['identifier']);
        if (isset($query['ip_address']))  $filters['ip_address']  = trim((string) $query['ip_address']);
        if (isset($query['successful']) && $query['successful'] !== '') {
            $filters['successful'] = (int) $query['successful'];
        }
        if (!empty($query['date_from'])) $filters['date_from'] = trim((string) $query['date_from']);
        if (!empty($query['date_to']))   $filters['date_to']   = trim((string) $query['date_to']);

        $result = $this->service->listLoginAttempts($pagination['page'], $pagination['per_page'], $filters);
        Response::success($result['items'], 'Login attempts fetched.', 200, $result['meta']);
    }

    public function stats(Request $request): void
    {
        Response::success($this->service->getStats(), 'Security stats fetched.');
    }
}
