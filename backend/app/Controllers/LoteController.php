<?php
// app/Controllers/LoteController.php
namespace App\Controllers;

use Core\Controller;
use App\Models\Lote;
use App\Models\Saida;

class LoteController extends Controller {
    private Lote  $model;
    private Saida $saidaModel;

    public function __construct() {
        $this->model      = new Lote();
        $this->saidaModel = new Saida();
    }

    public function index(): void {
        $status = $this->query('status');
        $data   = $status
            ? $this->model->byStatus(strtoupper($status))
            : $this->model->allWithDetails();
        $this->json($data);
    }

    public function show(string $id): void {
        $lote = $this->model->find((int) $id);
        $lote ? $this->json($lote) : $this->error('Lote nao encontrado', 404);
    }

    public function store(): void {
        $body = $this->body();
        $this->validate($body, ['produto_id', 'quantidade', 'data_validade']);

        if ((float) $body['quantidade'] <= 0) {
            $this->error('Quantidade deve ser maior que zero', 422);
            return;
        }

        // Código gerado automaticamente se não informado
        $body['codigo_lote'] = trim((string) ($body['codigo_lote'] ?? ''))
            ?: $this->model->nextCodigoLote((int) $body['produto_id']);

        // Status calculado pelo trigger MySQL — não precisa ser calculado aqui
        // Passamos apenas os dados; o trigger garante o status correto no INSERT

        $id = $this->model->insert($body);
        $this->json($this->model->find($id), 201);
    }

    public function update(string $id): void {
        $loteId = (int) $id;

        if (!$this->model->find($loteId)) {
            $this->error('Lote nao encontrado', 404);
            return;
        }

        $body = $this->body();

        // Remove status_validade do body — o trigger recalcula automaticamente
        unset($body['status_validade']);

        if ($body !== []) {
            $this->model->update($loteId, $body);
        }

        $this->json($this->model->find($loteId));
    }

    public function destroy(string $id): void {
        $loteId = (int) $id;

        if (!$this->model->find($loteId)) {
            $this->error('Lote nao encontrado', 404);
            return;
        }

        $combos = $this->model->comboCount($loteId);
        if ($combos > 0) {
            $this->error("Nao e possivel remover: lote possui {$combos} combo(s)", 409);
            return;
        }

        $this->model->deleteAlertas($loteId);
        $this->model->delete($loteId);
        $this->json(['deleted' => true]);
    }

    /**
     * POST /api/lotes/:id/saida
     * Registra saída manual de estoque (vencimento, quebra, doação, ajuste, outros).
     */
    public function saida(string $id): void {
        $loteId = (int) $id;
        $lote   = $this->model->find($loteId);

        if (!$lote) {
            $this->error('Lote nao encontrado', 404);
            return;
        }

        $body = $this->body();
        $this->validate($body, ['quantidade', 'motivo']);

        $quantidade = (float) $body['quantidade'];
        if ($quantidade <= 0) {
            $this->error('Quantidade deve ser maior que zero', 422);
            return;
        }

        if ($quantidade > (float) $lote['quantidade']) {
            $this->error('Quantidade de saida maior que o estoque disponivel', 422);
            return;
        }

        $motivosValidos = ['VENCIMENTO', 'QUEBRA', 'DOACAO', 'AJUSTE', 'OUTROS'];
        $motivo = strtoupper(trim((string) $body['motivo']));
        if (!in_array($motivo, $motivosValidos, true)) {
            $this->error('Motivo invalido. Use: ' . implode(', ', $motivosValidos), 422);
            return;
        }

        // Registra a saída e decrementa o estoque
        $saidaId = $this->saidaModel->insert([
            'lote_id'     => $loteId,
            'quantidade'  => $quantidade,
            'motivo'      => $motivo,
            'observacao'  => trim((string) ($body['observacao'] ?? '')),
        ]);

        $this->model->registrarSaida($loteId, $quantidade);

        $this->json([
            'saida_id'           => $saidaId,
            'lote_id'            => $loteId,
            'quantidade_baixada' => $quantidade,
            'motivo'             => $motivo,
            'estoque_restante'   => max(0, (float) $lote['quantidade'] - $quantidade),
        ], 201);
    }
}