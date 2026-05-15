<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Services\AuthService;
use App\Validation\Validator;

final class AuthController
{
    private AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function csrfToken(Request $request): void
    {
        $token = $this->authService->issueCsrfToken();

        Response::success([
            'csrf_token' => $token,
        ], 'CSRF token generated.');
    }

    public function login(Request $request): void
    {
        $payload = $request->json();
        $identifier = Validator::requiredString($payload, 'identifier', 3, 190);
        $password = Validator::requiredString($payload, 'password', 8, 255);

        $data = $this->authService->login($identifier, $password, $request);

        Response::success($data, 'Login successful.');
    }

    public function logout(Request $request): void
    {
        $this->authService->logout($request);

        Response::success(null, 'Logout successful.');
    }

    public function me(Request $request): void
    {
        $userId = (int) ($request->attribute('auth.user_id', 0) ?? 0);
        if ($userId <= 0) {
            throw new HttpException(401, 'unauthorized', 'Authentication is required.');
        }

        $data = $this->authService->me($userId);
        Response::success($data, 'Authenticated session.');
    }
}
