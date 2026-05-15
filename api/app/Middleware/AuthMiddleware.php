<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Env;
use App\Core\HttpException;
use App\Core\SessionManager;
use App\Http\Request;

final class AuthMiddleware
{
    private SessionManager $sessionManager;

    public function __construct(SessionManager $sessionManager)
    {
        $this->sessionManager = $sessionManager;
    }

    public function handle(Request $request): int
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new HttpException(401, 'session_missing', 'No active session.');
        }

        $userId = $_SESSION['user_id'] ?? null;
        if (!is_int($userId) && !ctype_digit((string) $userId)) {
            throw new HttpException(401, 'unauthorized', 'Authentication is required.');
        }

        $idleTimeout = Env::int('SESSION_IDLE_TIMEOUT', Env::int('SESSION_LIFETIME', 1800));
        $lastActivity = (int) ($_SESSION['last_activity'] ?? 0);
        if ($lastActivity > 0 && (time() - $lastActivity) > $idleTimeout) {
            $this->sessionManager->revokeCurrentSession();
            $this->sessionManager->destroyCurrentSession();
            throw new HttpException(401, 'session_expired', 'Session has expired.');
        }

        if (Env::bool('SESSION_BIND_FINGERPRINT', true)) {
            $stored = (string) ($_SESSION['fingerprint'] ?? '');
            $current = hash('sha256', $request->ipAddress() . '|' . $request->userAgent());
            if ($stored === '' || !hash_equals($stored, $current)) {
                $this->sessionManager->revokeCurrentSession();
                $this->sessionManager->destroyCurrentSession();
                throw new HttpException(401, 'session_invalid', 'Session validation failed.');
            }
        }

        $resolvedUserId = (int) $userId;
        $_SESSION['last_activity'] = time();
        $request->setAttribute('auth.user_id', $resolvedUserId);

        $this->sessionManager->touchMetadata($resolvedUserId, $request->ipAddress(), $request->userAgent());

        return $resolvedUserId;
    }
}
