<?php
// app/Controllers/SaidaController.php
namespace App\Controllers;

use Core\Controller;
use App\Models\Saida;

class SaidaController extends Controller {
    private Saida $model;

    public function __construct() {
        $this->model = new Saida();
    }

    // GET /api/saidas — histórico completo de saídas
    public function index(): void {
        $this->json($this->model->allWithDetails());
    }

    // GET /api/saidas/lote/:id — saídas de um lote específico
    public function byLote(string $id): void {
        $this->json($this->model->byLote((int) $id));
    }
}