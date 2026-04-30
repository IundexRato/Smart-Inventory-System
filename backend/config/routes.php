<?php
// config/routes.php
// Responsabilidade: declarar todas as rotas da API em um único lugar
// Padrão: /api/{recurso}/{:id?}

use Core\Router;
use App\Controllers\LoteController;
use App\Controllers\ComboController;
use App\Controllers\ProdutoController;
use App\Controllers\AlertaController;
use App\Controllers\DashboardController;

return function (Router $router): void {

    // ── Dashboard ─────────────────────────────────────────
    // GET /api/dashboard — KPIs + lotes em risco + combos ativos
    $router->get('/api/dashboard', [new DashboardController, 'index']);

    // ── Lotes ─────────────────────────────────────────────
    // GET    /api/lotes              — lista todos (aceita ?status=URGENTE)
    // GET    /api/lotes/:id          — detalhe de um lote
    // POST   /api/lotes              — cadastra novo lote
    // PUT    /api/lotes/:id          — atualiza lote
    // DELETE /api/lotes/:id          — remove lote
    $router->get   ('/api/lotes',      [new LoteController, 'index']);
    $router->get   ('/api/lotes/:id',  [new LoteController, 'show']);
    $router->post  ('/api/lotes',      [new LoteController, 'store']);
    $router->put   ('/api/lotes/:id',  [new LoteController, 'update']);
    $router->delete('/api/lotes/:id',  [new LoteController, 'destroy']);

    // ── Combos ────────────────────────────────────────────
    // GET    /api/combos             — lista combos (aceita ?status=ATIVO)
    // GET    /api/combos/:id         — detalhe
    // POST   /api/combos             — cria combo
    // PUT    /api/combos/:id/aprovar — aprova combo pendente
    // DELETE /api/combos/:id         — remove combo
    $router->get   ('/api/combos',             [new ComboController, 'index']);
    $router->get   ('/api/combos/:id',         [new ComboController, 'show']);
    $router->post  ('/api/combos',             [new ComboController, 'store']);
    $router->put   ('/api/combos/:id/aprovar', [new ComboController, 'aprovar']);
    $router->delete('/api/combos/:id',         [new ComboController, 'destroy']);

    // ── Produtos ──────────────────────────────────────────
    // GET  /api/produtos             — lista todos
    // GET  /api/produtos/:id         — detalhe
    // POST /api/produtos             — cadastra
    // PUT  /api/produtos/:id         — atualiza
    $router->get ('/api/produtos',     [new ProdutoController, 'index']);
    $router->get ('/api/produtos/:id', [new ProdutoController, 'show']);
    $router->post('/api/produtos',     [new ProdutoController, 'store']);
    $router->put ('/api/produtos/:id', [new ProdutoController, 'update']);

    // ── Alertas ───────────────────────────────────────────
    // GET /api/alertas               — lista (aceita ?enviado=0)
    // PUT /api/alertas/:id/marcar    — marca como enviado
    $router->get('/api/alertas',              [new AlertaController, 'index']);
    $router->put('/api/alertas/:id/marcar',   [new AlertaController, 'marcar']);
};
