<?php
// app/Controllers/CategoriaController.php
namespace App\Controllers;

use Core\Controller;
use App\Models\Categoria;

class CategoriaController extends Controller {
    private Categoria $model;

    public function __construct() {
        $this->model = new Categoria();
    }

    public function index(): void {
        $this->json($this->model->allWithCounts());
    }

    public function show(string $id): void {
        $categoria = $this->model->find((int) $id);
        $categoria ? $this->json($categoria) : $this->error('Categoria nao encontrada', 404);
    }

    public function store(): void {
        $body = $this->body();
        $this->validate($body, ['nome']);

        $nome = trim((string) $body['nome']);
        $prefixo = strtoupper(trim((string) ($body['prefixo'] ?? substr($nome, 0, 3))));

        if ($prefixo === '' || strlen($prefixo) > 5) {
            $this->error('Prefixo deve ter entre 1 e 5 caracteres', 422);
            return;
        }

        if ($this->model->findByNome($nome)) {
            $this->error('Categoria ja existe', 409);
            return;
        }

        $id = $this->model->insert([
            'nome' => $nome,
            'prefixo' => $prefixo,
        ]);

        $this->json($this->model->find($id), 201);
    }

    public function update(string $id): void {
        $categoriaId = (int) $id;
        if (!$this->model->find($categoriaId)) {
            $this->error('Categoria nao encontrada', 404);
            return;
        }

        $body = $this->body();
        $data = [];

        if (isset($body['nome'])) {
            $nome = trim((string) $body['nome']);
            if ($nome === '') {
                $this->error('Nome da categoria e obrigatorio', 422);
                return;
            }
            if ($this->model->findByNome($nome, $categoriaId)) {
                $this->error('Categoria ja existe', 409);
                return;
            }
            $data['nome'] = $nome;
        }

        if (isset($body['prefixo'])) {
            $prefixo = strtoupper(trim((string) $body['prefixo']));
            if ($prefixo === '' || strlen($prefixo) > 5) {
                $this->error('Prefixo deve ter entre 1 e 5 caracteres', 422);
                return;
            }
            $data['prefixo'] = $prefixo;
        }

        if ($data !== []) {
            $this->model->update($categoriaId, $data);
        }

        $this->json($this->model->find($categoriaId));
    }

    public function destroy(string $id): void {
        $categoriaId = (int) $id;
        if (!$this->model->find($categoriaId)) {
            $this->error('Categoria nao encontrada', 404);
            return;
        }

        $produtos = $this->model->produtoCount($categoriaId);
        if ($produtos > 0) {
            $this->error("Nao e possivel remover: categoria possui {$produtos} produto(s)", 409);
            return;
        }

        $this->model->delete($categoriaId);
        $this->json(['deleted' => true]);
    }
}
