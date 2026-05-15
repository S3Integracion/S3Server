<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Services\ExtensionAuthService;
use App\Services\OrderImportService;
use App\Services\OrderPendingService;
use App\Validation\Validator;

final class ExtensionController
{
    private ExtensionAuthService $authService;
    private OrderImportService $importService;
    private OrderPendingService $pendingService;

    public function __construct(
        ExtensionAuthService $authService,
        OrderImportService $importService,
        OrderPendingService $pendingService
    ) {
        $this->authService = $authService;
        $this->importService = $importService;
        $this->pendingService = $pendingService;
    }

    public function login(Request $request): void
    {
        $payload = $request->json();
        $identifier = Validator::requiredString($payload, 'identifier', 3, 190);
        $password = Validator::requiredString($payload, 'password', 8, 255);
        $deviceLabel = Validator::optionalString($payload, 'device', 0, 120);

        $data = $this->authService->login($identifier, $password, $deviceLabel, $request);

        Response::success($data, 'Extension login successful.');
    }

    public function logout(Request $request): void
    {
        $this->authService->logout($request);
        Response::success(null, 'Extension token revoked.');
    }

    public function me(Request $request): void
    {
        $userId = (int) ($request->attribute('auth.user_id', 0) ?? 0);
        if ($userId <= 0) {
            throw new HttpException(401, 'unauthorized', 'Authentication is required.');
        }

        $data = $this->authService->me($userId);
        Response::success($data, 'Authenticated extension session.');
    }

    public function import(Request $request): void
    {
        $userId = (int) ($request->attribute('auth.user_id', 0) ?? 0);
        if ($userId <= 0) {
            throw new HttpException(401, 'unauthorized', 'Authentication is required.');
        }

        $payload = $request->json();
        $result = $this->importService->import($userId, $payload, $request);

        Response::success($result, 'Order data imported.');
    }

    public function submitNotRegistered(Request $request): void
    {
        $userId = (int) ($request->attribute('auth.user_id', 0) ?? 0);
        if ($userId <= 0) {
            throw new HttpException(401, 'unauthorized', 'Authentication is required.');
        }

        $payload = $request->json();
        $result = $this->pendingService->submitNotRegistered($payload);

        Response::success($result, 'Not-registered orders submitted.');
    }

    public function submitNotDelivered(Request $request): void
    {
        $userId = (int) ($request->attribute('auth.user_id', 0) ?? 0);
        if ($userId <= 0) {
            throw new HttpException(401, 'unauthorized', 'Authentication is required.');
        }

        $payload = $request->json();
        $result = $this->pendingService->submitNotDelivered($payload);

        Response::success($result, 'Not-delivered orders submitted.');
    }
}
