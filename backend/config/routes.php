<?php
// config/routes.php
// Responsabilidade: declarar todas as rotas da API em um único lugar

use Core\Router;
use App\Controllers\LoteController;
use App\Controllers\ComboController;
use App\Controllers\VendaController;
use App\Controllers\ProdutoController;
use App\Controllers\AlertaController;
use App\Controllers\DashboardController;
use App\Controllers\CategoriaController;
use App\Controllers\FornecedorController;
use App\Controllers\SaidaController;

return function (Router $router): void {

    // ── Dashboard ─────────────────────────────────────────
    $router->get('/api/dashboard', [new DashboardController, 'index']);

    // ── Lotes ─────────────────────────────────────────────
    $router->get   ('/api/lotes',      [new LoteController, 'index']);
    $router->get   ('/api/lotes/:id',  [new LoteController, 'show']);
    $router->post  ('/api/lotes',      [new LoteController, 'store']);
    $router->put   ('/api/lotes/:id',  [new LoteController, 'update']);
    $router->delete('/api/lotes/:id',  [new LoteController, 'destroy']);

    // ── Combos ────────────────────────────────────────────
    $router->get   ('/api/combos',             [new ComboController, 'index']);
    $router->get   ('/api/combos/:id',         [new ComboController, 'show']);
    $router->post  ('/api/combos',             [new ComboController, 'store']);
    $router->put   ('/api/combos/:id',         [new ComboController, 'update']);
    $router->put   ('/api/combos/:id/aprovar', [new ComboController, 'aprovar']);
    $router->delete('/api/combos/:id',         [new ComboController, 'destroy']);

    // ── Produtos ──────────────────────────────────────────
    $router->get   ('/api/produtos',     [new ProdutoController, 'index']);
    $router->get   ('/api/produtos/:id', [new ProdutoController, 'show']);
    $router->post  ('/api/produtos',     [new ProdutoController, 'store']);
    $router->put   ('/api/produtos/:id', [new ProdutoController, 'update']);
    $router->delete('/api/produtos/:id', [new ProdutoController, 'destroy']);

    // ── Categorias ────────────────────────────────────────
    $router->get   ('/api/categorias',     [new CategoriaController, 'index']);
    $router->get   ('/api/categorias/:id', [new CategoriaController, 'show']);
    $router->post  ('/api/categorias',     [new CategoriaController, 'store']);
    $router->put   ('/api/categorias/:id', [new CategoriaController, 'update']);
    $router->delete('/api/categorias/:id', [new CategoriaController, 'destroy']);

    // ── Fornecedores ──────────────────────────────────────
    // GET    /api/fornecedores[?ativo=1|0]
    // GET    /api/fornecedores/:id
    // POST   /api/fornecedores
    // PUT    /api/fornecedores/:id
    // DELETE /api/fornecedores/:id
    $router->get   ('/api/fornecedores',     [new FornecedorController, 'index']);
    $router->get   ('/api/fornecedores/:id', [new FornecedorController, 'show']);
    $router->post  ('/api/fornecedores',     [new FornecedorController, 'store']);
    $router->put   ('/api/fornecedores/:id', [new FornecedorController, 'update']);
    $router->delete('/api/fornecedores/:id', [new FornecedorController, 'destroy']);

    // ── Vendas / Histórico ────────────────────────────────
    // GET /api/vendas/historico[?data_ini=&data_fim=&mes=&ano=&produto_id=&order=asc|desc]
    // GET /api/vendas/resumo-mensal[?meses=12]
    // GET /api/vendas/top-produtos[?data_ini=&data_fim=&limite=10]
    $router->get('/api/vendas/historico',     [new VendaController, 'historico']);
    $router->get('/api/vendas/resumo-mensal', [new VendaController, 'resumoMensal']);
    $router->get('/api/vendas/top-produtos',  [new VendaController, 'topProdutos']);

    // ── Saídas de estoque ────────────────────────────────────
    // POST   /api/lotes/:id/saida  — registra saída manual
    // GET    /api/saidas           — histórico completo
    // GET    /api/saidas/lote/:id  — saídas de um lote
    $router->post('/api/lotes/:id/saida',    [new LoteController,  'saida']);
    $router->get ('/api/saidas',             [new SaidaController, 'index']);
    $router->get ('/api/saidas/lote/:id',    [new SaidaController, 'byLote']);

    // ── Alertas ───────────────────────────────────────────
    $router->get('/api/alertas',            [new AlertaController, 'index']);
    $router->put('/api/alertas/:id/marcar', [new AlertaController, 'marcar']);
};