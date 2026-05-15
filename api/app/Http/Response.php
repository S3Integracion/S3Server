<?php
declare(strict_types=1);

namespace App\Http;

final class Response
{
    public static function json(array $payload, int $statusCode = 200, array $headers = []): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');

        foreach ($headers as $header => $value) {
            header($header . ': ' . $value);
        }

        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            $encoded = '{"success":false,"error":{"code":"encoding_error","message":"Unable to encode response."}}';
            http_response_code(500);
        }

        echo $encoded;
    }

    public static function success(
        mixed $data = null,
        string $message = 'OK',
        int $statusCode = 200,
        array $meta = []
    ): void {
        self::json([
            'success' => true,
            'data' => $data,
            'message' => $message,
            'meta' => $meta,
        ], $statusCode);
    }

    public static function error(string $errorCode, string $message, int $statusCode, array $meta = []): void
    {
        self::json([
            'success' => false,
            'error' => [
                'code' => $errorCode,
                'message' => $message,
            ],
            'meta' => $meta,
        ], $statusCode);
    }
}
