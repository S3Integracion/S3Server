<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\DashboardRepository;

final class AdminDashboardService
{
    public function __construct(private DashboardRepository $repo) {}

    public function getStats(): array
    {
        $stats       = $this->repo->getStats();
        $recentAudit = $this->repo->getRecentAudit(10);

        return [
            'stats'        => $stats,
            'recent_audit' => $recentAudit,
        ];
    }
}
