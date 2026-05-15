<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\AdminDashboardService;

final class AdminDashboardController
{
    public function __construct(private AdminDashboardService $service) {}

    public function stats(Request $request): void
    {
        Response::success($this->service->getStats(), 'Dashboard stats fetched.');
    }
}
