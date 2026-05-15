<?php
declare(strict_types=1);

namespace App\Services;

use App\Http\Request;
use App\Repositories\AuditRepository;
use App\Core\Logger;

final class AuditService
{
    private AuditRepository $auditRepository;
    private Logger $logger;

    public function __construct(AuditRepository $auditRepository, Logger $logger)
    {
        $this->auditRepository = $auditRepository;
        $this->logger = $logger;
    }

    public function record(
        ?int $userId,
        string $event,
        string $resource,
        string $action,
        Request $request,
        array $meta = []
    ): void {
        try {
            $this->auditRepository->record(
                $userId,
                $event,
                $resource,
                $action,
                $request->ipAddress(),
                $request->userAgent(),
                $meta
            );
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to persist audit record', [
                'event' => $event,
                'error' => $exception->getMessage(),
            ]);
        }

        $this->logger->audit($event, [
            'user_id' => $userId,
            'resource' => $resource,
            'action' => $action,
            'ip_address' => $request->ipAddress(),
            'meta' => $meta,
        ]);
    }
}
