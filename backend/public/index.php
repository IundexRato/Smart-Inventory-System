<?php
// public/index.php
// Responsabilidade: ponto de entrada único — inicializa autoload, router e despacha

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

set_exception_handler(function (Throwable $e): void {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
});

// ── Autoload simples (sem Composer) ───────────────────────
// Mapeia namespaces para pastas:
//   Core\Database  → core/Database.php
//   App\Models\... → app/Models/....php
spl_autoload_register(function (string $class): void {
    $map = [
        'Core\\'           => BASE_PATH . '/core/',
        'App\\Controllers\\' => BASE_PATH . '/app/Controllers/',
        'App\\Models\\'    => BASE_PATH . '/app/Models/',
        'App\\Services\\'  => BASE_PATH . '/app/Services/',
    ];

    foreach ($map as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
            $file = $dir . $relative . '.php';
            if (file_exists($file)) {
                require_once $file;
                return;
            }
        }
    }
});

// ── Inicializa Router e carrega rotas ─────────────────────
$router = new Core\Router();
$registerRoutes = require BASE_PATH . '/config/routes.php';
$registerRoutes($router);

// ── Despacha a requisição ─────────────────────────────────
$router->dispatch();
