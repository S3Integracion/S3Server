<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use App\Core\HttpException;
use App\Http\Request;
use App\Repositories\ApiTokenRepository;
use App\Repositories\PermissionRepository;
use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use App\Security\LoginRateLimiter;

/**
 * Issues and revokes Bearer tokens for the Chrome extension.
 *
 * The extension authenticates against the same `users` table that powers the
 * web portal (S3INTEGRACION). This guarantees a single identity per employee:
 * no shadow accounts, the same RBAC, the same lockout rules, the same audit
 * trail. Only the session vehicle changes (cookie -> Bearer) because cookies
 * with SameSite=Strict cannot travel from chrome-extension:// origins.
 */
final class ExtensionAuthService
{
    private UserRepository $userRepository;
    private RoleRepository $roleRepository;
    private PermissionRepository $permissionRepository;
    private ApiTokenRepository $tokenRepository;
    private LoginRateLimiter $loginRateLimiter;
    private AuditService $auditService;

    public function __construct(
        UserRepository $userRepository,
        RoleRepository $roleRepository,
        PermissionRepository $permissionRepository,
        ApiTokenRepository $tokenRepository,
        LoginRateLimiter $loginRateLimiter,
        AuditService $auditService
    ) {
        $this->userRepository = $userRepository;
        $this->roleRepository = $roleRepository;
        $this->permissionRepository = $permissionRepository;
        $this->tokenRepository = $tokenRepository;
        $this->loginRateLimiter = $loginRateLimiter;
        $this->auditService = $auditService;
    }

    public function login(string $identifier, string $password, ?string $deviceLabel, Request $request): array
    {
        $ipAddress = $request->ipAddress();
        $userAgent = $request->userAgent();

        $this->loginRateLimiter->ensureCanAttempt($identifier, $ipAddress);

        $user = $this->userRepository->findByIdentifier($identifier);
        if ($user === null) {
            $this->loginRateLimiter->recordFailure($identifier, $ipAddress, null, 'user_not_found');
            $this->auditService->record(null, 'auth.extension.login.failed', 'auth', 'extension_login', $request, [
                'identifier' => $identifier,
                'reason' => 'user_not_found',
            ]);
            throw new HttpException(401, 'invalid_credentials', 'Invalid credentials.');
        }

        $userId = (int) ($user['id'] ?? 0);
        $isActive = (int) ($user['is_active'] ?? 0) === 1;

        if (!$isActive) {
            $this->loginRateLimiter->recordFailure($identifier, $ipAddress, $userId, 'user_inactive');
            $this->auditService->record($userId, 'auth.extension.login.failed', 'auth', 'extension_login', $request, [
                'identifier' => $identifier,
                'reason' => 'user_inactive',
            ]);
            throw new HttpException(403, 'user_inactive', 'The user is inactive.');
        }

        $passwordHash = (string) ($user['password_hash'] ?? '');
        if ($passwordHash === '' || !password_verify($password, $passwordHash)) {
            $this->loginRateLimiter->recordFailure($identifier, $ipAddress, $userId, 'password_invalid');
            $this->auditService->record($userId, 'auth.extension.login.failed', 'auth', 'extension_login', $request, [
                'identifier' => $identifier,
                'reason' => 'password_invalid',
            ]);
            throw new HttpException(401, 'invalid_credentials', 'Invalid credentials.');
        }

        $tokenPlain = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $tokenPlain);
        $ttl = max(60, Env::int('EXTENSION_TOKEN_TTL_SECONDS', 28800));
        $created = $this->tokenRepository->create($userId, $tokenHash, $deviceLabel, $ttl);

        $this->userRepository->touchLastLogin($userId);
        $this->loginRateLimiter->recordSuccess($identifier, $ipAddress, $userId);

        $roles = $this->roleRepository->getRoleNamesByUserId($userId);
        $permissions = $this->permissionRepository->getPermissionNamesByUserId($userId);

        $this->auditService->record($userId, 'auth.extension.login.success', 'auth', 'extension_login', $request, [
            'identifier' => $identifier,
            'token_id' => $created['id'],
            'device_label' => $deviceLabel,
        ]);

        return [
            'access_token' => $tokenPlain,
            'token_type' => 'Bearer',
            'expires_at' => $created['expires_at'],
            'user' => [
                'id' => $userId,
                'username' => (string) ($user['username'] ?? ''),
                'email' => (string) ($user['email'] ?? ''),
            ],
            'roles' => $roles,
            'permissions' => $permissions,
        ];
    }

    public function logout(Request $request): void
    {
        $tokenHash = (string) ($request->attribute('auth.token_hash', '') ?? '');
        if ($tokenHash === '') {
            return;
        }

        $userId = (int) ($request->attribute('auth.user_id', 0) ?? 0);
        $this->tokenRepository->revokeByHash($tokenHash);
        $this->auditService->record($userId > 0 ? $userId : null, 'auth.extension.logout', 'auth', 'extension_logout', $request, []);
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
        ];
    }
}
