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

    public function index(): void {
        $status = $this->query('status');
        $this->json($this->model->allWithDetails($status ? strtoupper($status) : null));
    }

    public function show(string $id): void {
        $combo = $this->model->find((int) $id);
        $combo ? $this->json($combo) : $this->error('Combo nao encontrado', 404);
    }

    public function store(): void {
        $body = $this->body();
        $this->validate($body, ['lote_id', 'produto_parceiro_id', 'preco_combo', 'valido_ate']);

        $body['status'] = $this->validStatus($body['status'] ?? 'PENDENTE');

        $id = $this->model->insert($body);
        $this->json($this->model->find($id), 201);
    }

    public function update(string $id): void {
        $comboId = (int) $id;

        if (!$this->model->find($comboId)) {
            $this->error('Combo nao encontrado', 404);
            return;
        }

        $body = $this->body();
        if (isset($body['status'])) {
            $body['status'] = $this->validStatus($body['status']);
        }

        if ($body !== []) {
            $this->model->update($comboId, $body);
        }

        $this->json($this->model->find($comboId));
    }

    public function aprovar(string $id): void {
        $body = $this->body();
        $this->model->aprovar((int) $id, $body['aprovado_por'] ?? 'sistema');
        $this->json($this->model->find((int) $id));
    }

    public function destroy(string $id): void {
        if (!$this->model->find((int) $id)) {
            $this->error('Combo nao encontrado', 404);
            return;
        }

        $this->model->delete((int) $id);
        $this->json(['deleted' => true]);
    }

    private function validStatus(string $status): string {
        $status = strtoupper($status);
        $allowed = ['PENDENTE', 'APROVADO', 'ATIVO', 'ENCERRADO', 'REJEITADO'];

        if (!in_array($status, $allowed, true)) {
            $this->error('Status de combo invalido', 422);
        }

        return $status;
    }
}
