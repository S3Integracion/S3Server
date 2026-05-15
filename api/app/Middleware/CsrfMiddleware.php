<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\HttpException;
use App\Http\Request;

final class CsrfMiddleware
{
    public function handle(Request $request): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new HttpException(401, 'session_missing', 'Session is required for CSRF validation.');
        }

        $sessionToken = (string) ($_SESSION['csrf_token'] ?? '');
        $headerToken = (string) ($request->header('x-csrf-token', '') ?? '');

        if ($sessionToken === '' || $headerToken === '' || !hash_equals($sessionToken, $headerToken)) {
            throw new HttpException(403, 'csrf_invalid', 'CSRF token is missing or invalid.');
        }
    }
}
