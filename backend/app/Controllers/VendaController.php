<?php
// app/Controllers/VendaController.php
namespace App\Controllers;

use Core\Controller;
use App\Models\Venda;
use App\Models\Produto;

class VendaController extends Controller {
    private Venda   $model;
    private Produto $produtoModel;

    public function __construct() {
        $this->model        = new Venda();
        $this->produtoModel = new Produto();
    }

    // GET /api/vendas/historico
    // Params: data_ini, data_fim, mes, ano, produto_id, order (asc|desc)
    public function historico(): void {
        $filtros = [
            'data_ini'   => $this->query('data_ini'),
            'data_fim'   => $this->query('data_fim'),
            'mes'        => $this->query('mes'),
            'ano'        => $this->query('ano'),
            'produto_id' => $this->query('produto_id'),
            'order'      => $this->query('order', 'desc'),
        ];

        // Limpa nulos
        $filtros = array_filter($filtros, fn($v) => $v !== null && $v !== '');

        $this->json($this->model->historico($filtros));
    }

    // GET /api/vendas/resumo-mensal[?meses=12]
    public function resumoMensal(): void {
        $meses = (int) ($this->query('meses') ?? 12);
        $this->json($this->model->resumoMensal($meses));
    }

    // GET /api/vendas/top-produtos[?data_ini=&data_fim=&limite=10]
    public function topProdutos(): void {
        $this->json($this->model->topProdutos(
            (int) ($this->query('limite') ?? 10),
            $this->query('data_ini'),
            $this->query('data_fim'),
        ));
    }

    // GET /api/vendas/produtos  — lista produtos que têm vendas (para o filtro)
    public function produtosComVendas(): void {
        $this->json($this->produtoModel->allWithDetails());
    }
}
