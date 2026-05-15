<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\HttpException;
use App\Http\Request;

final class CorsMiddleware
{
    /**
     * @var string[]
     */
    private array $allowedOrigins;
    private bool $allowCredentials;

    /**
     * @param string[] $allowedOrigins
     */
    public function __construct(array $allowedOrigins, bool $allowCredentials = true)
    {
        $this->allowedOrigins = array_values(array_unique(array_filter(array_map(
            static fn(string $origin): string => rtrim(trim($origin), '/'),
            $allowedOrigins
        ))));
        $this->allowCredentials = $allowCredentials;
    }

    public function handle(Request $request): bool
    {
        $origin = $request->origin();
        if ($origin === null) {
            return false;
        }

        if (!$this->isAllowed($origin)) {
            throw new HttpException(403, 'cors_origin_denied', 'Origin is not allowed.');
        }

        $isExtensionOrigin = str_starts_with($origin, 'chrome-extension://');

        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, X-Requested-With, Authorization');
        header('Access-Control-Max-Age: 600');

        // The extension authenticates with Bearer tokens (no cookies).
        // Sending Allow-Credentials would force the browser to attempt cookie
        // delivery, which never works for chrome-extension:// origins anyway.
        if ($this->allowCredentials && !$isExtensionOrigin) {
            header('Access-Control-Allow-Credentials: true');
        }

        if ($request->method() === 'OPTIONS') {
            http_response_code(204);
            return true;
        }

        return false;
    }

    private function isAllowed(string $origin): bool
    {
        if (empty($this->allowedOrigins)) {
            return false;
        }

        foreach ($this->allowedOrigins as $allowed) {
            if ($allowed === $origin) {
                return true;
            }
            if ($allowed === 'chrome-extension://*' && str_starts_with($origin, 'chrome-extension://')) {
                return true;
            }
        }

        return false;
    }
}
