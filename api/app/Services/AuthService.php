<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Core\SessionManager;
use App\Http\Request;
use App\Repositories\PermissionRepository;
use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use App\Security\LoginRateLimiter;

final class AuthService
{
    private UserRepository $userRepository;
    private RoleRepository $roleRepository;
    private PermissionRepository $permissionRepository;
    private LoginRateLimiter $loginRateLimiter;
    private AuditService $auditService;
    private SessionManager $sessionManager;

    public function __construct(
        UserRepository $userRepository,
        RoleRepository $roleRepository,
        PermissionRepository $permissionRepository,
        LoginRateLimiter $loginRateLimiter,
        AuditService $auditService,
        SessionManager $sessionManager
    ) {
        $this->userRepository = $userRepository;
        $this->roleRepository = $roleRepository;
        $this->permissionRepository = $permissionRepository;
        $this->loginRateLimiter = $loginRateLimiter;
        $this->auditService = $auditService;
        $this->sessionManager = $sessionManager;
    }

    public function issueCsrfToken(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new HttpException(401, 'session_missing', 'Session is required.');
        }

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION['csrf_token'];
    }

    public function login(string $identifier, string $password, Request $request): array
    {
        $ipAddress = $request->ipAddress();
        $userAgent = $request->userAgent();

        $user = $this->userRepository->findByIdentifier($identifier);
        $userId = $user !== null ? (int) ($user['id'] ?? 0) : null;
        $isActive = $user !== null && (int) ($user['is_active'] ?? 0) === 1;
        $passwordHash = $user !== null ? (string) ($user['password_hash'] ?? '') : '';
        $isValidPassword = $passwordHash !== '' && password_verify($password, $passwordHash);

        // Allow valid credentials even if prior failures reached the lockout
        // threshold, so the real account owner is not locked out.
        if ($user !== null && $isActive && $isValidPassword) {
            $userId = (int) ($user['id'] ?? 0);

            session_regenerate_id(true);

            $_SESSION['user_id'] = $userId;
            $_SESSION['username'] = (string) ($user['username'] ?? '');
            $_SESSION['authenticated_at'] = time();
            $_SESSION['last_activity'] = time();
            $_SESSION['fingerprint'] = hash('sha256', $ipAddress . '|' . $userAgent);
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            $this->sessionManager->touchMetadata($userId, $ipAddress, $userAgent);

            $this->userRepository->touchLastLogin($userId);
            $this->loginRateLimiter->recordSuccess($identifier, $ipAddress, $userId);

            $roles = $this->roleRepository->getRoleNamesByUserId($userId);
            $permissions = $this->permissionRepository->getPermissionNamesByUserId($userId);

            $this->auditService->record($userId, 'auth.login.success', 'auth', 'login', $request, [
                'identifier' => $identifier,
            ]);

            return [
                'user' => [
                    'id' => $userId,
                    'username' => (string) ($user['username'] ?? ''),
                    'email' => (string) ($user['email'] ?? ''),
                ],
                'roles' => $roles,
                'permissions' => $permissions,
                'csrf_token' => (string) $_SESSION['csrf_token'],
            ];
        }

        $this->loginRateLimiter->ensureCanAttempt($identifier, $ipAddress);

        if ($user === null) {
            $this->loginRateLimiter->recordFailure($identifier, $ipAddress, null, 'user_not_found');
            $this->auditService->record(null, 'auth.login.failed', 'auth', 'login', $request, [
                'identifier' => $identifier,
                'reason' => 'user_not_found',
            ]);
            throw new HttpException(401, 'invalid_credentials', 'Invalid credentials.');
        }

        $userId = (int) ($user['id'] ?? 0);

        if (!$isActive) {
            $this->loginRateLimiter->recordFailure($identifier, $ipAddress, $userId, 'user_inactive');
            $this->auditService->record($userId, 'auth.login.failed', 'auth', 'login', $request, [
                'identifier' => $identifier,
                'reason' => 'user_inactive',
            ]);
            throw new HttpException(403, 'user_inactive', 'The user is inactive.');
        }

        $this->loginRateLimiter->recordFailure($identifier, $ipAddress, $userId, 'password_invalid');
        $this->auditService->record($userId, 'auth.login.failed', 'auth', 'login', $request, [
            'identifier' => $identifier,
            'reason' => 'password_invalid',
        ]);
        throw new HttpException(401, 'invalid_credentials', 'Invalid credentials.');
    }

    public function me(int $userId): array
    {
        $user = $this->userRepository->findForAdminById($userId);
        if ($user === null) {
            throw new HttpException(401, 'session_user_missing', 'Authenticated user no longer exists.');
        }

        if ((int) ($user['is_active'] ?? 0) !== 1) {
            throw new HttpException(403, 'user_inactive', 'The user is inactive.');
        }

        return [
            'user' => [
                'id' => (int) $user['id'],
                'username' => (string) $user['username'],
                'email' => (string) $user['email'],
                'last_login_at' => $user['last_login_at'] ?? null,
            ],
            'roles' => $this->roleRepository->getRoleNamesByUserId($userId),
            'permissions' => $this->permissionRepository->getPermissionNamesByUserId($userId),
            'csrf_token' => $this->issueCsrfToken(),
        ];
    }

    public function logout(Request $request): void
    {
        $userId = null;
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user_id'])) {
            $userId = (int) $_SESSION['user_id'];
        }

        $this->auditService->record($userId, 'auth.logout', 'auth', 'logout', $request, []);

        $this->sessionManager->revokeCurrentSession();
        $this->sessionManager->destroyCurrentSession();
    }
}
