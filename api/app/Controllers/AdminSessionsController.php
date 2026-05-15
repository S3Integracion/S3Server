<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\AdminSessionService;
use App\Validation\Validator;

final class AdminSessionsController
{
    public function __construct(private AdminSessionService $service) {}

    public function index(Request $request): void
    {
        $query      = $request->allQuery();
        $pagination = Validator::pagination($query);
        $activeOnly = isset($query['active_only']) && $query['active_only'] !== '0';

        $result = $this->service->listSessions(
            $pagination['page'],
            $pagination['per_page'],
            $pagination['search'],
            $activeOnly
        );

        Response::success($result['items'], 'Sessions fetched.', 200, $result['meta']);
    }

    public function revoke(Request $request): void
    {
        $sessionId = Validator::requiredString(
            ['id' => $request->routeParam('id')],
            'id',
            1,
            128
        );

        $actorId = (int) ($request->attribute('auth.user_id', 0) ?? 0);
        $this->service->revokeSession($sessionId, $actorId, $request);

        Response::success(null, 'Session revoked.');
    }

    public function revokeAll(Request $request): void
    {
        $actorId = (int) ($request->attribute('auth.user_id', 0) ?? 0);
        $count   = $this->service->revokeAll($actorId, $request);

        Response::success(['revoked_count' => $count], 'All sessions revoked.');
    }
}
