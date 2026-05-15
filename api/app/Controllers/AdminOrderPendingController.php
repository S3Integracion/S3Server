<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\OrderPendingService;

final class AdminOrderPendingController
{
    public function __construct(private OrderPendingService $pendingService) {}

    public function notRegistered(Request $request): void
    {
        $page = max(1, (int) ($request->query('page', 1)));
        $perPage = max(1, min(500, (int) ($request->query('per_page', 50))));

        $result = $this->pendingService->listNotRegistered($page, $perPage);
        Response::success($result, 'Orders not registered.');
    }

    public function notDelivered(Request $request): void
    {
        $page = max(1, (int) ($request->query('page', 1)));
        $perPage = max(1, min(500, (int) ($request->query('per_page', 50))));

        $result = $this->pendingService->listNotDelivered($page, $perPage);
        Response::success($result, 'Orders not delivered.');
    }
}
