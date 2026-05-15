<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

final class SessionManager
{
    private PDO $pdo;
    private Logger $logger;
    private ?DatabaseSessionHandler $handler = null;

    public function __construct(PDO $pdo, Logger $logger)
    {
        $this->pdo = $pdo;
        $this->logger = $logger;
    }

    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $lifetime = Env::int('SESSION_LIFETIME', 1800);
        $secure = Env::bool('SESSION_COOKIE_SECURE', true);
        $httpOnly = Env::bool('SESSION_COOKIE_HTTP_ONLY', true);
        $sameSite = Env::get('SESSION_COOKIE_SAMESITE', 'Strict') ?? 'Strict';

        $sessionName = Env::get('SESSION_NAME', 'S3SESSID') ?? 'S3SESSID';

        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.cookie_httponly', $httpOnly ? '1' : '0');
        ini_set('session.cookie_secure', $secure ? '1' : '0');
        ini_set('session.cookie_samesite', $sameSite);
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '100');
        ini_set('session.gc_maxlifetime', (string) $lifetime);
        ini_set('session.sid_length', '64');
        ini_set('session.sid_bits_per_character', '5');

        session_name($sessionName);

        $this->handler = new DatabaseSessionHandler($this->pdo, $lifetime);
        session_set_save_handler($this->handler, true);

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => $httpOnly,
            'samesite' => $sameSite,
        ]);

        session_start();
    }

    public function touchMetadata(?int $userId, string $ipAddress, string $userAgent): void
    {
        if ($this->handler === null || session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $sessionId = session_id();
        if ($sessionId === '') {
            return;
        }

        try {
            $this->handler->syncMetadata($sessionId, $userId, $ipAddress, $userAgent);
        } catch (\Throwable $exception) {
            $this->logger->warning('Unable to sync session metadata', [
                'message' => $exception->getMessage(),
            ]);
        }
    }

    public function revokeCurrentSession(): void
    {
        if ($this->handler === null || session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $sessionId = session_id();
        if ($sessionId === '') {
            return;
        }

        try {
            $this->handler->revoke($sessionId);
        } catch (\Throwable $exception) {
            $this->logger->warning('Unable to revoke session', [
                'message' => $exception->getMessage(),
            ]);
        }
    }

    public function destroyCurrentSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'] ?? '/',
                $params['domain'] ?? '',
                (bool) ($params['secure'] ?? false),
                (bool) ($params['httponly'] ?? true)
            );
        }

        session_destroy();
    }
}
