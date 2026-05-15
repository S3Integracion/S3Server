<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Http\Request;
use App\Repositories\AdminTokenReadRepository;

final class AdminTokenService
{
    public function __construct(
        private AdminTokenReadRepository $repo,
        private AuditService $auditService
    ) {}

    public function listTokens(int $page, int $perPage, array $filters): array
    {
        $total  = $this->repo->count($filters);
        $offset = ($page - 1) * $perPage;
        $items  = $this->repo->list($perPage, $offset, $filters);

        foreach ($items as &$t) {
            $t['status'] = $t['revoked_at'] !== null ? 'revoked'
                : (strtotime($t['expires_at']) < time() ? 'expired' : 'active');
        }
        unset($t);

        return [
            'items' => $items,
            'meta'  => [
                'total'    => $total,
                'page'     => $page,
                'per_page' => $perPage,
                'pages'    => $perPage > 0 ? (int) ceil($total / $perPage) : 1,
            ],
        ];
    }

    public function revokeToken(int $tokenId, int $actorId, Request $request): void
    {
        $token = $this->repo->findById($tokenId);
        if ($token === null) {
            throw new HttpException(404, 'not_found', 'Token not found.');
        }
        if ($token['revoked_at'] !== null) {
            throw new HttpException(409, 'already_revoked', 'Token is already revoked.');
        }

        $this->repo->revokeById($tokenId);

        $this->auditService->record(
            $actorId,
            'admin.tokens.revoked',
            'api_token',
            'revoke',
            $request,
            ['token_id' => $tokenId, 'token_user_id' => $token['user_id']]
        );
    }
}
