<?php
// app/Controllers/AlertaController.php
// Responsabilidade: receber requisições HTTP de /api/alertas e devolver JSON
// Não contém regras de negócio — delega para o Model

namespace App\Controllers;

use Core\Controller;
use App\Models\Alerta;

class AlertaController extends Controller {

    private Alerta $model;

    public function __construct() {
        $this->model = new Alerta();
    }

    // GET /api/alertas
    // Aceita ?enviado=0 para filtrar apenas alertas pendentes de envio
    public function index(): void {
        $enviado = $this->query('enviado');
        $filtro  = $enviado !== null ? (int) $enviado : null;

        $this->json($this->model->allWithDetails($filtro));
    }

    // PUT /api/alertas/:id/marcar
    // Marca o alerta como enviado (registra timestamp)
    public function marcar(string $id): void {
        $alerta = $this->model->find((int) $id);

        if (!$alerta) {
            $this->error('Alerta não encontrado', 404);
            return;
        }

        $this->model->marcarEnviado((int) $id);
        $this->json(['marked' => true, 'id' => (int) $id]);
    }
}
