<?php
// core/Router.php
// Responsabilidade: receber a URL, identificar o controller/método correto e despachar

namespace Core;

class Router {
    private array $routes = [];

    // Registra uma rota: método HTTP + padrão de URL + callback
    public function add(string $method, string $pattern, callable $handler): void {
        $this->routes[] = [
            'method'  => strtoupper($method),
            'pattern' => $this->compilePattern($pattern),
            'handler' => $handler,
        ];
    }

    // Atalhos semânticos
    public function get(string $pattern, callable $handler): void {
        $this->add('GET', $pattern, $handler);
    }
    public function post(string $pattern, callable $handler): void {
        $this->add('POST', $pattern, $handler);
    }
    public function put(string $pattern, callable $handler): void {
        $this->add('PUT', $pattern, $handler);
    }
    public function delete(string $pattern, callable $handler): void {
        $this->add('DELETE', $pattern, $handler);
    }

    // Executa o despacho — chamado pelo front controller (public/index.php)
    public function dispatch(): void {
        $method = strtoupper($_SERVER['REQUEST_METHOD']);
        $uri = $this->requestPath();

        // Suporte a _method override para clientes que só suportam GET/POST
        if ($method === 'POST' && isset($_POST['_method'])) {
            $method = strtoupper($_POST['_method']);
        }

        // Headers CORS — permite que o frontend (porta diferente) consuma a API
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');

        if ($method === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) continue;

            if (preg_match($route['pattern'], $uri, $matches)) {
                $params = [];
                foreach ($matches as $key => $value) {
                    if (is_int($key) && $key > 0) {
                        $params[] = $value;
                    }
                }

                call_user_func_array($route['handler'], $params);
                return;
            }
        }

        // Nenhuma rota encontrada
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Rota não encontrada']);
    }

    // Converte padrão /lotes/:id em regex
    private function compilePattern(string $pattern): string {
        $pattern = preg_replace('/\/:([a-zA-Z_]+)/', '/(?P<$1>[^/]+)', $pattern);
        return '#^' . $pattern . '$#';
    }

    private function requestPath(): string {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $path = '/' . trim($path, '/');

        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $scriptName = '/' . trim($scriptName, '/');
        $scriptDir = str_replace('\\', '/', rtrim(dirname($scriptName), '/\\'));
        $backendDir = '';

        if ($scriptDir === '.' || $scriptDir === '/') {
            $scriptDir = '';
        }

        if ($scriptDir !== '' && str_ends_with($scriptDir, '/public')) {
            $backendDir = substr($scriptDir, 0, -strlen('/public'));
        }

        $prefixes = array_unique(array_filter([$scriptName, $scriptDir, $backendDir]));
        usort($prefixes, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        foreach ($prefixes as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                $path = substr($path, strlen($prefix)) ?: '/';
                break;
            }
        }

        if ($path === '/index.php') {
            return '/';
        }

        if (str_starts_with($path, '/index.php/')) {
            $path = substr($path, strlen('/index.php')) ?: '/';
        }

        return '/' . trim($path, '/');
    }
}
