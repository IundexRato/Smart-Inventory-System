<?php
// app/Controllers/ProdutoController.php
namespace App\Controllers;

use Core\Controller;
use App\Models\Produto;

class ProdutoController extends Controller {
    private Produto $model;

    public function __construct() {
        $this->model = new Produto();
    }

    public function index(): void {
        $this->json($this->model->allWithDetails());
    }

    public function show(string $id): void {
        $produto = $this->model->find((int) $id);

        if (!$produto) {
            $this->error('Produto nao encontrado', 404);
            return;
        }

        $produto['afinidades'] = $this->model->afinidades((int) $id);
        $this->json($produto);
    }

    public function store(): void {
        $body = $this->body();
        $this->validate($body, ['categoria_id', 'nome']);

        $data = $this->produtoData($body);
        $data['sku'] = strtoupper(trim((string) ($data['sku'] ?? '')));

        if ($data['sku'] === '') {
            $data['sku'] = $this->model->nextSku((int) $data['categoria_id']);
        }

        if ($this->model->findBySku($data['sku'])) {
            $this->error('SKU ja esta em uso por outro produto', 409);
            return;
        }

        $id = $this->model->insert($data);
        $this->json($this->model->find($id), 201);
    }

    public function update(string $id): void {
        $produtoId = (int) $id;

        if (!$this->model->find($produtoId)) {
            $this->error('Produto nao encontrado', 404);
            return;
        }

        $data = $this->produtoData($this->body(), false);

        if (isset($data['sku'])) {
            $data['sku'] = strtoupper(trim((string) $data['sku']));
            if ($data['sku'] === '') {
                $this->error('SKU nao pode ser vazio', 422);
                return;
            }
            if ($this->model->findBySku($data['sku'], $produtoId)) {
                $this->error('SKU ja esta em uso por outro produto', 409);
                return;
            }
        }

        if ($data !== []) {
            $this->model->update($produtoId, $data);
        }

        $this->json($this->model->find($produtoId));
    }

    public function destroy(string $id): void {
        $produtoId = (int) $id;

        if (!$this->model->find($produtoId)) {
            $this->error('Produto nao encontrado', 404);
            return;
        }

        $lotes = $this->model->loteCount($produtoId);
        if ($lotes > 0) {
            $this->error("Nao e possivel remover: produto possui {$lotes} lote(s)", 409);
            return;
        }

        $combos = $this->model->comboParceiroCount($produtoId);
        if ($combos > 0) {
            $this->error("Nao e possivel remover: produto e parceiro em {$combos} combo(s)", 409);
            return;
        }

        $this->model->deleteAfinidades($produtoId);
        $this->model->delete($produtoId);
        $this->json(['deleted' => true]);
    }

    private function produtoData(array $body, bool $withDefaults = true): array {
        $allowed = ['categoria_id', 'sku', 'nome', 'descricao', 'unidade_medida', 'peso', 'preco_custo', 'preco_venda', 'ativo'];
        $data = [];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $body)) {
                $data[$field] = $body[$field];
            }
        }

        if ($withDefaults) {
            $data['unidade_medida'] = $data['unidade_medida'] ?? 'UN';
            $data['preco_custo'] = $data['preco_custo'] ?? 0;
            $data['preco_venda'] = $data['preco_venda'] ?? 0;
            $data['peso'] = $data['peso'] ?? 0;
        }

        return $data;
    }
}
