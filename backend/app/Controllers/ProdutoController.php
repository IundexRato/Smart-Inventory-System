<?php
// app/Controllers/ProdutoController.php
// Responsabilidade: receber requisições HTTP de /api/produtos e devolver JSON
// Não contém regras de negócio — delega para o Model

namespace App\Controllers;

use Core\Controller;
use App\Models\Produto;

class ProdutoController extends Controller {

    private Produto $model;

    public function __construct() {
        $this->model = new Produto();
    }

    // GET /api/produtos
    public function index(): void {
        $this->json($this->model->allWithDetails());
    }

    // GET /api/produtos/:id
    // Inclui afinidades do produto no retorno
    public function show(string $id): void {
        $produto = $this->model->find((int) $id);

        if (!$produto) {
            $this->error('Produto não encontrado', 404);
            return;
        }

        $produto['afinidades'] = $this->model->afinidades((int) $id);
        $this->json($produto);
    }

    // POST /api/produtos
    // Body esperado: { categoria_id, sku, nome, unidade_medida, preco_custo, preco_venda }
    public function store(): void {
        $body = $this->body();
        $this->validate($body, ['categoria_id', 'sku', 'nome', 'preco_custo', 'preco_venda']);

        $id = $this->model->insert($body);
        $this->json($this->model->find($id), 201);
    }

    // PUT /api/produtos/:id
    // Body esperado: campos a atualizar (parcial permitido)
    public function update(string $id): void {
        $lote = $this->model->find((int) $id);

        if (!$lote) {
            $this->error('Produto não encontrado', 404);
            return;
        }

        $this->model->update((int) $id, $this->body());
        $this->json($this->model->find((int) $id));
    }
}
