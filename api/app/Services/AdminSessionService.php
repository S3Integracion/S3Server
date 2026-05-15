<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Http\Request;
use App\Repositories\SessionRepository;

final class AdminSessionService
{
    public function __construct(
        private SessionRepository $sessionRepository,
        private AuditService $auditService
    ) {}

    public function listSessions(int $page, int $perPage, string $search, bool $activeOnly): array
    {
        $total  = $this->sessionRepository->countSessions($search, $activeOnly);
        $offset = ($page - 1) * $perPage;
        $items  = $this->sessionRepository->listSessions($perPage, $offset, $search, $activeOnly);

        foreach ($items as &$s) {
            $s['last_activity_human'] = $s['last_activity']
                ? date('Y-m-d H:i:s', (int) $s['last_activity'])
                : null;
            $s['is_active'] = $s['revoked_at'] === null
                && (int) $s['last_activity'] > (time() - (int) ($_ENV['SESSION_IDLE_TIMEOUT'] ?? 1800));
        }
        unset($s);

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

    public function revokeSession(string $sessionId, int $actorId, Request $request): void
    {
        $session = $this->sessionRepository->findById($sessionId);
        if ($session === null) {
            throw new HttpException(404, 'not_found', 'Session not found.');
        }

        $this->sessionRepository->revokeById($sessionId);

        $this->auditService->record(
            $actorId,
            'admin.sessions.revoked',
            'session',
            'revoke',
            $request,
            ['session_id' => $sessionId, 'session_user_id' => $session['user_id']]
        );
    }

    public function revokeAll(int $actorId, Request $request): int
    {
        $count = $this->sessionRepository->revokeAll();

        $this->auditService->record(
            $actorId,
            'admin.sessions.revoke_all',
            'session',
            'revoke_all',
            $request,
            ['revoked_count' => $count]
        );

        return $count;
    }
}
