<?php
declare(strict_types=1);

use App\Core\HttpException;
use App\Core\Router;
use App\Http\Request;

$container = require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'bootstrap.php';

/** @var \App\Core\ErrorHandler $errorHandler */
$errorHandler = $container['error_handler'];

try {
    $request = Request::fromGlobals();

    $container['middlewares']['security_headers']->apply();

    if ($container['middlewares']['cors']->handle($request)) {
        return;
    }

    $router = new Router();
    require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'routes.php';
    registerApiRoutes($router, $container['controllers']);

    $matched = $router->dispatch($request->method(), $request->path());
    $request->setRouteParams($matched['params']);

    $options = $matched['options'];
    $authMode = (string) ($options['auth_mode'] ?? 'session');

    $expectsJson = (bool) ($options['expects_json'] ?? false);
    if ($expectsJson) {
        $contentType = $request->contentType();
        if (!str_contains($contentType, 'application/json')) {
            throw new HttpException(415, 'unsupported_media_type', 'Content-Type must be application/json.');
        }
    }

    if ((bool) ($options['requires_auth'] ?? false)) {
        if ($authMode === 'bearer') {
            $container['middlewares']['bearer_auth']->handle($request);
        } else {
            $container['middlewares']['auth']->handle($request);
        }
    }

    // CSRF only applies to cookie/session-backed routes; Bearer requests do
    // not carry cookies so they are not subject to CSRF.
    if ($authMode !== 'bearer' && (bool) ($options['requires_csrf'] ?? false)) {
        $container['middlewares']['csrf']->handle($request);
    }

    $requiredPermission = $options['required_permission'] ?? null;
    if (is_string($requiredPermission) && $requiredPermission !== '') {
        $container['middlewares']['permission']->handle($request, $requiredPermission);
    }

    $handler = $matched['handler'];
    $handler($request);
} catch (Throwable $exception) {
    $errorHandler->render($exception);
}
