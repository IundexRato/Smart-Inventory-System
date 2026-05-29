<?php
// app/Controllers/FornecedorController.php
namespace App\Controllers;

use Core\Controller;
use App\Models\Fornecedor;

class FornecedorController extends Controller {
    private Fornecedor $model;

    public function __construct() {
        $this->model = new Fornecedor();
    }

    // GET /api/fornecedores[?ativo=1|0]
    public function index(): void {
        $ativo = $this->query('ativo');
        $filtro = $ativo !== null ? (int) $ativo : null;
        $this->json($this->model->allWithStatus($filtro));
    }

    // GET /api/fornecedores/:id
    public function show(string $id): void {
        $f = $this->model->find((int) $id);
        $f ? $this->json($f) : $this->error('Fornecedor nao encontrado', 404);
    }

    // POST /api/fornecedores
    public function store(): void {
        $body = $this->body();
        $this->validate($body, ['razao_social']);

        $data = $this->fornecedorData($body);

        if (!empty($data['cnpj']) && $this->model->findByCnpj($data['cnpj'])) {
            $this->error('CNPJ ja cadastrado', 409);
            return;
        }

        $id = $this->model->insert($data);
        $this->json($this->model->find($id), 201);
    }

    // PUT /api/fornecedores/:id
    public function update(string $id): void {
        $fornecedorId = (int) $id;
        if (!$this->model->find($fornecedorId)) {
            $this->error('Fornecedor nao encontrado', 404);
            return;
        }

        $body = $this->body();
        $data = $this->fornecedorData($body, false);

        if (!empty($data['cnpj']) && $this->model->findByCnpj($data['cnpj'], $fornecedorId)) {
            $this->error('CNPJ ja esta em uso por outro fornecedor', 409);
            return;
        }

        if ($data !== []) {
            $this->model->update($fornecedorId, $data);
        }

        $this->json($this->model->find($fornecedorId));
    }

    // DELETE /api/fornecedores/:id
    public function destroy(string $id): void {
        $fornecedorId = (int) $id;
        if (!$this->model->find($fornecedorId)) {
            $this->error('Fornecedor nao encontrado', 404);
            return;
        }

        $lotes = $this->model->loteCount($fornecedorId);
        if ($lotes > 0) {
            $this->error("Nao e possivel remover: fornecedor possui {$lotes} lote(s) vinculado(s)", 409);
            return;
        }

        $this->model->delete($fornecedorId);
        $this->json(['deleted' => true]);
    }

    private function fornecedorData(array $body, bool $withDefaults = true): array {
        $allowed = ['razao_social', 'cnpj', 'contato', 'email', 'telefone', 'nome_responsavel', 'ativo'];
        $data = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $body)) {
                $data[$field] = $body[$field];
            }
        }
        if ($withDefaults) {
            $data['ativo'] = $data['ativo'] ?? 1;
        }
        return $data;
    }
}
