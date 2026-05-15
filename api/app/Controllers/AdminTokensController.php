<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\AdminTokenService;
use App\Validation\Validator;

final class AdminTokensController
{
    public function __construct(private AdminTokenService $service) {}

    public function index(Request $request): void
    {
        $query      = $request->allQuery();
        $pagination = Validator::pagination($query);

        $filters = [];
        if (!empty($query['user_id']))    $filters['user_id']    = (int) $query['user_id'];
        if (!empty($query['active_only'])) $filters['active_only'] = true;

        $result = $this->service->listTokens($pagination['page'], $pagination['per_page'], $filters);
        Response::success($result['items'], 'Tokens fetched.', 200, $result['meta']);
    }

    public function revoke(Request $request): void
    {
        $tokenId = Validator::routeInt($request->routeParam('id'), 'id');
        $actorId = (int) ($request->attribute('auth.user_id', 0) ?? 0);

        $this->service->revokeToken($tokenId, $actorId, $request);
        Response::success(null, 'Token revoked.');
    }
}
