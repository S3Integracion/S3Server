<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

class HttpException extends RuntimeException
{
    private int $statusCode;
    private string $errorCode;
    private array $meta;
    private array $headers;

    public function __construct(
        int $statusCode,
        string $errorCode,
        string $message,
        array $meta = [],
        array $headers = []
    ) {
        parent::__construct($message);
        $this->statusCode = $statusCode;
        $this->errorCode = $errorCode;
        $this->meta = $meta;
        $this->headers = $headers;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getMeta(): array
    {
        return $this->meta;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }
}
