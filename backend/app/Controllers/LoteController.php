<?php
// app/Controllers/LoteController.php
namespace App\Controllers;
use Core\Controller;
use App\Models\Lote;
use App\Services\FefoService;

class LoteController extends Controller {
    private Lote        $model;
    private FefoService $fefo;

    public function __construct() {
        $this->model = new Lote();
        $this->fefo  = new FefoService();
    }

    // GET /api/lotes?status=URGENTE
    public function index(): void {
        $status = $this->query('status');
        $data   = $status
            ? $this->model->byStatus(strtoupper($status))
            : $this->model->allWithDetails();
        $this->json($data);
    }

    // GET /api/lotes/:id
    public function show(string $id): void {
        $lote = $this->model->find((int) $id);
        $lote ? $this->json($lote) : $this->error('Lote não encontrado', 404);
    }

    // POST /api/lotes
    public function store(): void {
        $body = $this->body();
        $this->validate($body, ['produto_id', 'codigo_lote', 'quantidade', 'data_validade']);

        $body['status_validade'] = $this->fefo->classificarStatus($body['data_validade']);
        $id = $this->model->insert($body);
        $this->json($this->model->find($id), 201);
    }

    // PUT /api/lotes/:id
    public function update(string $id): void {
        $body = $this->body();
        if (isset($body['data_validade'])) {
            $body['status_validade'] = $this->fefo->classificarStatus($body['data_validade']);
        }
        $this->model->update((int) $id, $body);
        $this->json($this->model->find((int) $id));
    }

    // DELETE /api/lotes/:id
    public function destroy(string $id): void {
        $this->model->delete((int) $id);
        $this->json(['deleted' => true]);
    }
}
