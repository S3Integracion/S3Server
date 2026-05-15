<?php
declare(strict_types=1);

namespace App\Core;

use App\Http\Response;
use ErrorException;
use Throwable;

final class ErrorHandler
{
    private Logger $logger;
    private bool $debug;

    public function __construct(Logger $logger, bool $debug)
    {
        $this->logger = $logger;
        $this->debug = $debug;
    }

    public function register(): void
    {
        set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler(function (Throwable $exception): void {
            $this->render($exception);
        });
    }

    public function render(Throwable $exception): void
    {
        if ($exception instanceof HttpException) {
            foreach ($exception->getHeaders() as $header => $value) {
                header($header . ': ' . $value);
            }

            Response::error(
                $exception->getErrorCode(),
                $exception->getMessage(),
                $exception->getStatusCode(),
                $exception->getMeta()
            );
            return;
        }

        $this->logger->error('Unhandled exception', [
            'type' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ]);

        $message = $this->debug ? $exception->getMessage() : 'Internal server error.';

        Response::error(
            'internal_error',
            $message,
            500
        );
    }
}
