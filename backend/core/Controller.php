<?php
// core/Controller.php
// Responsabilidade: métodos utilitários de resposta HTTP/JSON
// Todos os Controllers da pasta app/Controllers/ herdam esta classe

namespace Core;

abstract class Controller {

    // Envia resposta JSON padronizada com sucesso
    protected function json(mixed $data, int $status = 200): void {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'data'    => $data,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    // Envia resposta JSON de erro
    protected function error(string $message, int $status = 400): void {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error'   => $message,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Lê o body da requisição como JSON e retorna array
    protected function body(): array {
        $raw = file_get_contents('php://input');
        return json_decode($raw, true) ?? [];
    }

    // Retorna parâmetros GET sanitizados
    protected function query(string $key, mixed $default = null): mixed {
        return isset($_GET[$key]) ? htmlspecialchars($_GET[$key]) : $default;
    }

    // Valida campos obrigatórios no body
    protected function validate(array $data, array $required): bool {
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $this->error("Campo obrigatório ausente: {$field}", 422);
                return false;
            }
        }
        return true;
    }
}
