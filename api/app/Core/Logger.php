<?php
declare(strict_types=1);

namespace App\Core;

final class Logger
{
    private string $logDirectory;

    public function __construct(string $logDirectory)
    {
        $this->logDirectory = rtrim($logDirectory, DIRECTORY_SEPARATOR);
        if (!is_dir($this->logDirectory)) {
            mkdir($this->logDirectory, 0750, true);
        }
    }

    public function info(string $message, array $context = []): void
    {
        $this->write('app.log', 'INFO', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->write('app.log', 'WARNING', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('app.log', 'ERROR', $message, $context);
    }

    public function audit(string $message, array $context = []): void
    {
        $this->write('audit.log', 'AUDIT', $message, $context);
    }

    private function write(string $fileName, string $level, string $message, array $context): void
    {
        $payload = [
            'timestamp' => gmdate('c'),
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];

        $line = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if ($line === false) {
            return;
        }

        $path = $this->logDirectory . DIRECTORY_SEPARATOR . $fileName;
        file_put_contents($path, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
