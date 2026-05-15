<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Env;

final class SecurityHeadersMiddleware
{
    public function apply(): void
    {
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: no-referrer');
        header('Permissions-Policy: geolocation=(), camera=(), microphone=()');
        header('Cross-Origin-Opener-Policy: same-origin');
        header('Cross-Origin-Resource-Policy: same-origin');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');

        if (Env::bool('SECURITY_ENABLE_CSP', true)) {
            header(
                "Content-Security-Policy: default-src 'self'; " .
                "script-src 'self'; style-src 'self'; img-src 'self' data:; " .
                "font-src 'self'; connect-src 'self'; frame-ancestors 'none'; " .
                "base-uri 'self'; form-action 'self'"
            );
        }

        if (Env::bool('SECURITY_ENABLE_HSTS', true) && $this->isHttps()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }
    }

    private function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }

        $forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
        return strtolower((string) $forwardedProto) === 'https';
    }
}
