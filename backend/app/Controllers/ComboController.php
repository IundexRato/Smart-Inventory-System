<?php
// app/Controllers/ComboController.php

namespace App\Controllers;

use Core\Controller;
use App\Models\Combo;

class ComboController extends Controller {
    private Combo $model;

    public function __construct() {
        $this->model = new Combo();
    }

    // GET /api/combos?status=ATIVO
    public function index(): void {
        $status = $this->query('status');
        $this->json($this->model->allWithDetails($status));
    }

    // GET /api/combos/:id
    public function show(string $id): void {
        $combo = $this->model->find((int) $id);
        $combo ? $this->json($combo) : $this->error('Combo nao encontrado', 404);
    }

    // POST /api/combos
    public function store(): void {
        $body = $this->body();
        $this->validate($body, ['lote_id', 'produto_parceiro_id', 'preco_combo', 'valido_ate']);
        $body['status'] = 'PENDENTE';

        $id = $this->model->insert($body);
        $this->json($this->model->find($id), 201);
    }

    // PUT /api/combos/:id/aprovar
    public function aprovar(string $id): void {
        $body = $this->body();
        $this->model->aprovar((int) $id, $body['aprovado_por'] ?? 'sistema');
        $this->json($this->model->find((int) $id));
    }

    // DELETE /api/combos/:id
    public function destroy(string $id): void {
        $this->model->delete((int) $id);
        $this->json(['deleted' => true]);
    }
}
