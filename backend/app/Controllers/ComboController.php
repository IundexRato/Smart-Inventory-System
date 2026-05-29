<?php
// app/Controllers/ComboController.php
namespace App\Controllers;

use Core\Controller;
use App\Models\Combo;
use App\Models\Lote;
use App\Models\Produto;

class ComboController extends Controller {
    private Combo   $model;
    private Lote    $loteModel;
    private Produto $produtoModel;

    public function __construct() {
        $this->model        = new Combo();
        $this->loteModel    = new Lote();
        $this->produtoModel = new Produto();
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
        $this->validate($body, ['lote_id', 'valido_ate']);

        $body['status'] = $this->validStatus($body['status'] ?? 'PENDENTE');
        $body['desconto_combo'] = (float) ($body['desconto_combo'] ?? 0);

        // produto_parceiro_id é opcional (combo unitário tipo "pague 1 leve 2")
        $body['produto_parceiro_id'] = !empty($body['produto_parceiro_id'])
            ? (int) $body['produto_parceiro_id']
            : null;

        // Calcula preço automaticamente se não informado
        if (empty($body['preco_combo'])) {
            $body['preco_combo'] = $this->calcPrecoCombo(
                (int) $body['lote_id'],
                $body['produto_parceiro_id'],
                $body['desconto_combo']
            );
        }

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

        if (isset($body['produto_parceiro_id']) && $body['produto_parceiro_id'] === '') {
            $body['produto_parceiro_id'] = null;
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

    /**
     * Calcula o preço do combo automaticamente:
     * (preco_venda_produto_lote + preco_venda_parceiro) * (1 - desconto/100)
     * Se não há parceiro, usa só o preço do produto do lote.
     */
    private function calcPrecoCombo(int $loteId, ?int $parceiroId, float $desconto): float {
        $lote = $this->loteModel->find($loteId);
        if (!$lote) return 0.00;

        $produtoPrincipal = $this->produtoModel->find((int) $lote['produto_id']);
        $precoPrincipal   = (float) ($produtoPrincipal['preco_venda'] ?? 0);

        $precoParceiro = 0.00;
        if ($parceiroId) {
            $parceiro      = $this->produtoModel->find($parceiroId);
            $precoParceiro = (float) ($parceiro['preco_venda'] ?? 0);
        }

        $precoBase = $precoPrincipal + $precoParceiro;
        return round($precoBase * (1 - $desconto / 100), 2);
    }

    private function validStatus(string $status): string {
        $status  = strtoupper($status);
        $allowed = ['PENDENTE', 'APROVADO', 'ATIVO', 'ENCERRADO', 'REJEITADO'];
        if (!in_array($status, $allowed, true)) {
            $this->error('Status de combo invalido', 422);
        }
        return $status;
    }
}