<?php
declare(strict_types=1);

namespace App\Security;

use App\Core\Env;
use App\Core\HttpException;
use App\Repositories\LoginAttemptRepository;

final class LoginRateLimiter
{
    private LoginAttemptRepository $loginAttemptRepository;
    private int $maxAttempts;
    private int $windowSeconds;
    private int $lockoutSeconds;

    public function __construct(LoginAttemptRepository $loginAttemptRepository)
    {
        $this->loginAttemptRepository = $loginAttemptRepository;
        $this->maxAttempts = Env::int('LOGIN_MAX_ATTEMPTS', 5);
        $this->windowSeconds = Env::int('LOGIN_ATTEMPT_WINDOW_SECONDS', 900);
        $this->lockoutSeconds = Env::int('LOGIN_LOCKOUT_SECONDS', 900);
    }

    public function ensureCanAttempt(string $identifier, string $ipAddress): void
    {
        $stats = $this->loginAttemptRepository->getFailureStats(
            $identifier,
            $ipAddress,
            time() - $this->windowSeconds
        );

        $failures = (int) ($stats['failures'] ?? 0);
        $lastFailure = (int) ($stats['last_failure'] ?? 0);

        if ($failures < $this->maxAttempts) {
            return;
        }

        $retryAfter = ($lastFailure + $this->lockoutSeconds) - time();
        if ($retryAfter > 0) {
            throw new HttpException(
                429,
                'too_many_attempts',
                'Too many failed login attempts. Try again later.',
                ['retry_after_seconds' => $retryAfter],
                ['Retry-After' => (string) $retryAfter]
            );
        }
    }

    public function recordFailure(string $identifier, string $ipAddress, ?int $userId, string $reason): void
    {
        $this->loginAttemptRepository->record($identifier, $ipAddress, false, $userId, $reason);
    }

    public function recordSuccess(string $identifier, string $ipAddress, int $userId): void
    {
        $this->loginAttemptRepository->record($identifier, $ipAddress, true, $userId, 'success');
    }
}
