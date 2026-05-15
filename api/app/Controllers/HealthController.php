<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;

final class HealthController
{
    public function show(Request $request): void
    {
        Response::success([
            'status' => 'healthy',
            'service' => 's3-enterprise-api',
            'version' => 'v1',
            'timestamp_utc' => gmdate('c'),
        ], 'Service is healthy.');
    }
}
