<?php
declare(strict_types=1);

namespace App\Core;

final class Router
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private array $routes = [];

    public function add(string $method, string $pattern, callable $handler, array $options = []): void
    {
        $normalizedMethod = strtoupper($method);
        $normalizedPattern = $this->normalizePath($pattern);

        $this->routes[] = [
            'method' => $normalizedMethod,
            'pattern' => $normalizedPattern,
            'regex' => $this->compilePattern($normalizedPattern),
            'handler' => $handler,
            'options' => $options,
        ];
    }

    /**
     * @return array{handler: callable, options: array<string, mixed>, params: array<string, string>}
     */
    public function dispatch(string $method, string $path): array
    {
        $normalizedMethod = strtoupper($method);
        $normalizedPath = $this->normalizePath($path);
        $allowedMethods = [];

        foreach ($this->routes as $route) {
            $matches = [];
            if (!preg_match($route['regex'], $normalizedPath, $matches)) {
                continue;
            }

            if ($route['method'] !== $normalizedMethod) {
                $allowedMethods[] = $route['method'];
                continue;
            }

            $params = [];
            foreach ($matches as $key => $value) {
                if (!is_string($key)) {
                    continue;
                }
                $params[$key] = $value;
            }

            return [
                'handler' => $route['handler'],
                'options' => $route['options'],
                'params' => $params,
            ];
        }

        if (!empty($allowedMethods)) {
            $allowed = implode(', ', array_values(array_unique($allowedMethods)));
            throw new HttpException(
                405,
                'method_not_allowed',
                'HTTP method not allowed for this resource.',
                ['allowed_methods' => $allowedMethods],
                ['Allow' => $allowed]
            );
        }

        throw new HttpException(404, 'resource_not_found', 'Resource not found.');
    }

    private function normalizePath(string $path): string
    {
        $trimmed = trim($path);
        if ($trimmed === '') {
            return '/';
        }

        $normalized = '/' . ltrim($trimmed, '/');
        if ($normalized !== '/' && str_ends_with($normalized, '/')) {
            $normalized = rtrim($normalized, '/');
        }

        return $normalized;
    }

    private function compilePattern(string $pattern): string
    {
        $regex = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', static function (array $matches): string {
            return '(?P<' . $matches[1] . '>[^/]+)';
        }, $pattern);

        if (!is_string($regex)) {
            return '/^$/';
        }

        return '#^' . $regex . '$#';
    }
}
