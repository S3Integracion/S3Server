<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\HttpException;
use App\Http\Request;
use App\Repositories\ApiTokenRepository;

final class BearerAuthMiddleware
{
    private ApiTokenRepository $tokens;

    public function __construct(ApiTokenRepository $tokens)
    {
        $this->tokens = $tokens;
    }

    public function handle(Request $request): int
    {
        $header = (string) $request->header('authorization', '');
        if ($header === '') {
            throw new HttpException(401, 'token_missing', 'Bearer token is required.');
        }

        if (stripos($header, 'Bearer ') !== 0) {
            throw new HttpException(401, 'token_malformed', 'Authorization header must use the Bearer scheme.');
        }

        $token = trim(substr($header, 7));
        if ($token === '' || strlen($token) > 256) {
            throw new HttpException(401, 'token_malformed', 'Bearer token is malformed.');
        }

        $tokenHash = hash('sha256', $token);
        $row = $this->tokens->findValid($tokenHash);
        if ($row === null) {
            throw new HttpException(401, 'token_invalid', 'Bearer token is invalid or expired.');
        }

        $userId = (int) ($row['user_id'] ?? 0);
        if ($userId <= 0) {
            throw new HttpException(401, 'token_invalid', 'Bearer token is invalid or expired.');
        }

        $request->setAttribute('auth.user_id', $userId);
        $request->setAttribute('auth.token_id', (int) $row['id']);
        $request->setAttribute('auth.token_hash', $tokenHash);

        $this->tokens->touchLastUsed((int) $row['id'], $request->ipAddress(), $request->userAgent());

        return $userId;
    }
}
