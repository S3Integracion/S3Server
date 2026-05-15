<?php
declare(strict_types=1);

use App\Controllers\AdminAuditController;
use App\Controllers\AdminDashboardController;
use App\Controllers\AdminDatabaseController;
use App\Controllers\AdminImportsController;
use App\Controllers\AdminOrderPendingController;
use App\Controllers\AdminOrdersController;
use App\Controllers\AdminPermissionsController;
use App\Controllers\AdminRolesController;
use App\Controllers\AdminSecurityController;
use App\Controllers\AdminSessionsController;
use App\Controllers\AdminTokensController;
use App\Controllers\AdminUsersController;
use App\Controllers\AuthController;
use App\Controllers\ExtensionController;
use App\Controllers\HealthController;
use App\Core\Database;
use App\Core\Env;
use App\Core\ErrorHandler;
use App\Core\Logger;
use App\Core\SessionManager;
use App\Middleware\AuthMiddleware;
use App\Middleware\BearerAuthMiddleware;
use App\Middleware\CorsMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\PermissionMiddleware;
use App\Middleware\SecurityHeadersMiddleware;
use App\Repositories\AdminImportReadRepository;
use App\Repositories\AdminOrderReadRepository;
use App\Repositories\AdminSecurityRepository;
use App\Repositories\AdminTokenReadRepository;
use App\Repositories\ApiTokenRepository;
use App\Repositories\AuditReadRepository;
use App\Repositories\AuditRepository;
use App\Repositories\DashboardRepository;
use App\Repositories\DatabaseBrowserRepository;
use App\Repositories\ImportRunRepository;
use App\Repositories\LoginAttemptRepository;
use App\Repositories\MarketplaceRepository;
use App\Repositories\OrderPendingRepository;
use App\Repositories\OrderRepository;
use App\Repositories\PermissionRepository;
use App\Repositories\RoleRepository;
use App\Repositories\SessionRepository;
use App\Repositories\UserRepository;
use App\Security\LoginRateLimiter;
use App\Services\AdminAuditService;
use App\Services\AdminDashboardService;
use App\Services\AdminDatabaseService;
use App\Services\AdminImportService;
use App\Services\AdminOrderService;
use App\Services\AdminSecurityService;
use App\Services\AdminSessionService;
use App\Services\AdminTokenService;
use App\Services\AuditService;
use App\Services\AuthService;
use App\Services\ExtensionAuthService;
use App\Services\OrderImportService;
use App\Services\OrderPendingService;
use App\Services\PermissionService;
use App\Services\RoleAdminService;
use App\Services\UserAdminService;

if (!defined('API_BASE_PATH')) {
    define('API_BASE_PATH', dirname(__DIR__));
}
if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', dirname(API_BASE_PATH));
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';
    $filePath = API_BASE_PATH . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . $relativePath;

    if (is_file($filePath)) {
        require_once $filePath;
    }
});

Env::load(PROJECT_ROOT . DIRECTORY_SEPARATOR . '.env');

date_default_timezone_set(Env::get('APP_TIMEZONE', 'UTC') ?? 'UTC');

$logger = new Logger(API_BASE_PATH . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs');
$errorHandler = new ErrorHandler($logger, Env::bool('APP_DEBUG', false));
$errorHandler->register();

$pdo = Database::getConnection();
$ordersPdo = Database::getConnection('orders');

$sessionManager = new SessionManager($pdo, $logger);
$sessionManager->start();

$userRepository = new UserRepository($pdo);
$roleRepository = new RoleRepository($pdo);
$permissionRepository = new PermissionRepository($pdo);
$loginAttemptRepository = new LoginAttemptRepository($pdo);
$auditRepository = new AuditRepository($pdo);

// OrderDetails-bound repositories share the secondary connection.
$apiTokenRepository = new ApiTokenRepository($ordersPdo);
$marketplaceRepository = new MarketplaceRepository($ordersPdo);
$importRunRepository = new ImportRunRepository($ordersPdo);
$orderRepository = new OrderRepository($ordersPdo);
$orderPendingRepository = new OrderPendingRepository($ordersPdo);

$auditService = new AuditService($auditRepository, $logger);
$loginRateLimiter = new LoginRateLimiter($loginAttemptRepository);
$authService = new AuthService(
    $userRepository,
    $roleRepository,
    $permissionRepository,
    $loginRateLimiter,
    $auditService,
    $sessionManager
);
$userAdminService = new UserAdminService($userRepository, $roleRepository, $auditService);
$roleAdminService = new RoleAdminService($roleRepository, $permissionRepository, $auditService);
$permissionService = new PermissionService($permissionRepository);
$extensionAuthService = new ExtensionAuthService(
    $userRepository,
    $roleRepository,
    $permissionRepository,
    $apiTokenRepository,
    $loginRateLimiter,
    $auditService
);
$orderImportService = new OrderImportService(
    $ordersPdo,
    $importRunRepository,
    $orderRepository,
    $marketplaceRepository,
    $logger
);

// Admin panel repositories
$sessionRepository       = new SessionRepository($pdo);
$auditReadRepository     = new AuditReadRepository($pdo);
$dashboardRepository     = new DashboardRepository($pdo, $ordersPdo);
$adminOrderReadRepo      = new AdminOrderReadRepository($ordersPdo);
$adminImportReadRepo     = new AdminImportReadRepository($ordersPdo);
$adminTokenReadRepo      = new AdminTokenReadRepository($ordersPdo);
$adminSecurityRepository = new AdminSecurityRepository($pdo);
$databaseBrowserRepo     = new DatabaseBrowserRepository($pdo, $ordersPdo);

// Admin panel services
$adminDashboardService = new AdminDashboardService($dashboardRepository);
$adminSessionService   = new AdminSessionService($sessionRepository, $auditService);
$adminAuditService     = new AdminAuditService($auditReadRepository);
$adminOrderService     = new AdminOrderService($adminOrderReadRepo);
$adminImportService    = new AdminImportService($adminImportReadRepo);
$adminTokenService     = new AdminTokenService($adminTokenReadRepo, $auditService);
$adminSecurityService  = new AdminSecurityService($adminSecurityRepository);
$adminDatabaseService  = new AdminDatabaseService($databaseBrowserRepo);
$orderPendingService   = new OrderPendingService($orderPendingRepository);

$controllers = [
    'health' => new HealthController(),
    'auth' => new AuthController($authService),
    'admin_users' => new AdminUsersController($userAdminService),
    'admin_roles' => new AdminRolesController($roleAdminService),
    'admin_permissions' => new AdminPermissionsController($permissionService),
    'extension'        => new ExtensionController($extensionAuthService, $orderImportService, $orderPendingService),
    'admin_dashboard'  => new AdminDashboardController($adminDashboardService),
    'admin_sessions'   => new AdminSessionsController($adminSessionService),
    'admin_audit'      => new AdminAuditController($adminAuditService),
    'admin_database'   => new AdminDatabaseController($adminDatabaseService),
    'admin_orders'     => new AdminOrdersController($adminOrderService),
    'admin_imports'    => new AdminImportsController($adminImportService),
    'admin_tokens'     => new AdminTokensController($adminTokenService),
    'admin_security'       => new AdminSecurityController($adminSecurityService),
    'admin_order_pending'  => new AdminOrderPendingController($orderPendingService),
];

$allowedOrigins = Env::csv('ALLOWED_ORIGINS');
if (empty($allowedOrigins)) {
    $appUrl = rtrim((string) (Env::get('APP_URL', '') ?? ''), '/');
    if ($appUrl !== '') {
        $allowedOrigins[] = $appUrl;
    }
}

$middlewares = [
    'cors' => new CorsMiddleware($allowedOrigins, Env::bool('CORS_ALLOW_CREDENTIALS', true)),
    'security_headers' => new SecurityHeadersMiddleware(),
    'auth' => new AuthMiddleware($sessionManager),
    'bearer_auth' => new BearerAuthMiddleware($apiTokenRepository),
    'csrf' => new CsrfMiddleware(),
    'permission' => new PermissionMiddleware($permissionRepository),
];

return [
    'controllers' => $controllers,
    'middlewares' => $middlewares,
    'error_handler' => $errorHandler,
    'logger' => $logger,
];
