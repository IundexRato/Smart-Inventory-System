<?php
// app/Controllers/LoteController.php
namespace App\Controllers;

use Core\Controller;
use App\Models\Lote;
use App\Services\FefoService;

class LoteController extends Controller {
    private Lote $model;
    private FefoService $fefo;

    public function __construct() {
        $this->model = new Lote();
        $this->fefo = new FefoService();
    }

    public function index(): void {
        $status = $this->query('status');
        $data = $status
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

        $body['codigo_lote'] = trim((string) ($body['codigo_lote'] ?? '')) ?: $this->model->nextCodigoLote();
        $body['status_validade'] = $this->fefo->classificarStatus($body['data_validade']);

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
        if (isset($body['data_validade'])) {
            $body['status_validade'] = $this->fefo->classificarStatus($body['data_validade']);
        }

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
}
