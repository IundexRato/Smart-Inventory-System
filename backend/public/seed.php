<?php
// public/seed.php — popula o banco com dados de exemplo
// Acesse: http://localhost/{projeto}/backend/public/seed.php
// ⚠ REMOVA ESTE ARQUIVO EM PRODUÇÃO
// v2.2 — datas calculadas a partir de NOW() para sempre estarem atualizadas

declare(strict_types=1);
define('BASE_PATH', dirname(__DIR__));

set_time_limit(300);

spl_autoload_register(function (string $class): void {
    $map = [
        'Core\\'             => BASE_PATH . '/core/',
        'App\\Controllers\\' => BASE_PATH . '/app/Controllers/',
        'App\\Models\\'      => BASE_PATH . '/app/Models/',
        'App\\Services\\'    => BASE_PATH . '/app/Services/',
    ];
    foreach ($map as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $file = $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (file_exists($file)) { require_once $file; return; }
        }
    }
});

$pdo = \Core\Database::getInstance();

// ── Tela de confirmação ────────────────────────────────────
if (($_GET['confirmar'] ?? '') !== 'sim') { ?>
<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8">
<style>
  body{font-family:monospace;background:#0a0a0a;color:#f0f0f0;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
  .box{background:#141414;border:1px solid #2a2a2a;border-radius:12px;padding:2rem 2.5rem;max-width:520px;text-align:center}
  h2{color:#ff2d78;margin-bottom:.5rem}
  p{color:#888;font-size:.85rem;line-height:1.7}
  .warn{color:#f59e0b;margin:1rem 0;font-size:.8rem;background:rgba(245,158,11,.1);padding:.8rem;border-radius:6px;border:1px solid rgba(245,158,11,.2)}
  .info{color:#22c55e;font-size:.78rem;background:rgba(34,197,94,.08);padding:.6rem .8rem;border-radius:6px;border:1px solid rgba(34,197,94,.2);margin:.8rem 0}
  a{display:inline-block;margin-top:1.2rem;background:#ff2d78;color:#fff;padding:.6rem 1.4rem;border-radius:6px;text-decoration:none;font-weight:700}
  a:hover{background:#ff6ba8}
  code{background:#1e1e1e;padding:.1rem .4rem;border-radius:3px;font-size:.8rem}
</style></head><body>
<div class="box">
  <h2>⚠ Seed do Banco</h2>
  <p>Esta operação irá <strong>apagar todos os dados</strong> e repovoar o banco com dados de exemplo.</p>
  <div class="warn">🗑 Todas as tabelas serão truncadas antes da inserção.</div>
  <div class="info">
    📅 Datas calculadas a partir de <strong><?= date('d/m/Y') ?></strong><br>
    Os lotes e vendas sempre estarão atualizados para hoje.
  </div>
  <p style="font-size:.75rem;color:#555">Remova <code>public/seed.php</code> antes de ir a produção.</p>
  <a href="?confirmar=sim">Executar Seed</a>
</div></body></html>
<?php exit; }

// ── Limpa tabelas ──────────────────────────────────────────
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
foreach (['alertas','combos','itens_venda','vendas','afinidade_produtos','lotes','produtos','fornecedores','categorias'] as $t) {
    $pdo->exec("TRUNCATE TABLE `$t`");
}
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

// ── 1. CATEGORIAS ──────────────────────────────────────────
$pdo->exec("INSERT INTO categorias (nome, prefixo, descricao) VALUES
    ('Laticínios',        'LAT', 'Leite, queijos, iogurtes e derivados'),
    ('Frios e Embutidos', 'FRI', 'Presuntos, salames, mortadelas e frios em geral'),
    ('Padaria',           'PAD', 'Pães, croissants, bolos e confeitaria'),
    ('Bebidas',           'BEB', 'Sucos, refrigerantes, águas e bebidas em geral'),
    ('Hortifruti',        'HOR', 'Frutas, legumes e verduras frescas'),
    ('Mercearia',         'MER', 'Produtos secos, enlatados e mercearia geral'),
    ('Congelados',        'CON', 'Produtos ultracongelados e semi-prontos')");

// ── 2. FORNECEDORES ────────────────────────────────────────
$pdo->exec("INSERT INTO fornecedores (razao_social, cnpj, contato, email, telefone, nome_responsavel, ativo) VALUES
    ('Laticínios Sul Ltda',             '11.222.333/0001-44', 'Comercial',     'comercial@laticiniossul.com.br',  '(51) 3333-1000', 'Carlos Souza',    1),
    ('Frios Premium SA',                '55.666.777/0001-88', 'Vendas',        'vendas@friospremium.com.br',      '(51) 3333-2000', 'Mariana Lima',    1),
    ('Distribuidora Bebidas RS',        '99.000.111/0001-22', 'Atendimento',   'atendimento@bebidas-rs.com.br',   '(51) 3333-3000', 'Roberto Nunes',   1),
    ('Panificadora Artesanal Eireli',   '33.444.555/0001-66', 'Expedição',     'expedicao@panartesanal.com.br',   '(51) 9999-4000', 'Fernanda Costa',  1),
    ('HortiFruti Gaúcho Ltda',          '77.888.999/0001-11', 'SAC',           'sac@hortifruti-gaucho.com.br',    '(51) 9888-5000', 'João Almeida',    1),
    ('Mercearia Central Distribuidora', '22.111.000/0001-55', 'Representante', 'rep@merceariacentral.com.br',     '(51) 3212-6000', 'Patricia Mendes', 1),
    ('CongelaBem Indústria SA',         '44.333.222/0001-99', 'Logística',     'logistica@congelabem.com.br',     '(54) 3456-7000', 'Diego Ferreira',  1),
    ('Importadora Lácteos Ltda',        '66.555.444/0001-33', 'Internacional', 'intl@importadoralacteos.com.br',  '(11) 4567-8000', 'Ana Rodrigues',   0)");

// ── 3. PRODUTOS ────────────────────────────────────────────
$pdo->exec("INSERT INTO produtos (categoria_id, sku, nome, unidade_medida, peso, preco_custo, preco_venda) VALUES
    (1,'LAT001','Queijo Mussarela 500g',       'UN',0.500,12.00,19.90),
    (1,'LAT002','Iogurte Natural 170g',         'UN',0.170, 2.50, 4.90),
    (1,'LAT003','Leite Integral 1L',            'LT',1.020, 3.80, 5.90),
    (1,'LAT004','Requeijão Cremoso 200g',       'UN',0.200, 5.20, 8.50),
    (1,'LAT005','Manteiga com Sal 200g',        'UN',0.200, 7.00,12.90),
    (2,'FRI001','Presunto Cozido Fatiado 200g', 'UN',0.200, 6.00,10.50),
    (2,'FRI002','Salame Milano 100g',           'UN',0.100, 5.00, 9.00),
    (2,'FRI003','Mortadela Defumada 300g',      'UN',0.300, 4.50, 7.90),
    (2,'FRI004','Peito de Peru Light 150g',     'UN',0.150, 7.50,13.90),
    (3,'PAD001','Pão de Forma Integral 500g',   'UN',0.500, 3.50, 6.90),
    (3,'PAD002','Croissant Manteiga 4un',       'UN',0.180, 4.00, 7.50),
    (3,'PAD003','Bolo de Chocolate 400g',       'UN',0.400, 8.00,14.90),
    (3,'PAD004','Pão Francês 50g',              'UN',0.050, 0.50, 1.10),
    (4,'BEB001','Suco de Laranja Natural 1L',   'LT',1.050, 4.50, 8.90),
    (4,'BEB002','Refrigerante Cola 2L',         'LT',2.100, 3.00, 7.00),
    (4,'BEB003','Água Mineral 500ml',           'UN',0.510, 0.80, 2.50),
    (4,'BEB004','Isotônico Limão 500ml',        'UN',0.530, 2.20, 4.90),
    (5,'HOR001','Morango Bandeja 300g',         'UN',0.300, 5.00, 9.90),
    (5,'HOR002','Tomate Cereja 200g',           'UN',0.200, 3.80, 7.50),
    (5,'HOR003','Alface Americana UN',          'UN',0.250, 1.50, 3.90),
    (5,'HOR004','Banana Prata KG',              'KG',1.000, 2.80, 5.90),
    (6,'MER001','Arroz Branco 5KG',             'PCT',5.000,18.00,27.90),
    (6,'MER002','Feijão Carioca 1KG',           'PCT',1.000, 6.50,10.90),
    (6,'MER003','Azeite Extravirgem 500ml',     'UN',0.530,22.00,39.90),
    (7,'CON001','Pizza Margherita 460g',        'UN',0.460, 9.00,17.90),
    (7,'CON002','Nuggets de Frango 300g',       'UN',0.300, 8.50,15.90),
    (7,'CON003','Lasanha Bolonhesa 600g',       'UN',0.600,12.00,21.90)");

// ── 4. LOTES — datas relativas a NOW() ────────────────────
// Todas as validades e entradas são calculadas a partir da data atual,
// garantindo que os status URGENTE/CRITICO/ATENCAO/SEGURO estejam sempre corretos.

$pdo->exec("INSERT INTO lotes
    (produto_id, fornecedor_id, codigo_lote, quantidade, data_validade, data_entrada)
VALUES
    -- Laticínios
    (1, 1, 'LAT001-" . date('Ymd') . "-001',  80, DATE_ADD(CURDATE(), INTERVAL 45 DAY),  DATE_SUB(CURDATE(), INTERVAL 20 DAY)),
    (1, 1, 'LAT001-" . date('Ymd') . "-002',  25, DATE_ADD(CURDATE(), INTERVAL 18 DAY),  DATE_SUB(CURDATE(), INTERVAL  5 DAY)),
    (2, 1, 'LAT002-" . date('Ymd') . "-001', 120, DATE_ADD(CURDATE(), INTERVAL  2 DAY),  DATE_SUB(CURDATE(), INTERVAL  3 DAY)),
    (2, 8, 'LAT002-" . date('Ymd') . "-002',  60, DATE_ADD(CURDATE(), INTERVAL 12 DAY),  DATE_SUB(CURDATE(), INTERVAL  8 DAY)),
    (3, 1, 'LAT003-" . date('Ymd') . "-001', 200, DATE_ADD(CURDATE(), INTERVAL 60 DAY),  DATE_SUB(CURDATE(), INTERVAL 30 DAY)),
    (4, 8, 'LAT004-" . date('Ymd') . "-001',  40, DATE_ADD(CURDATE(), INTERVAL  7 DAY),  DATE_SUB(CURDATE(), INTERVAL  2 DAY)),
    (5, 1, 'LAT005-" . date('Ymd') . "-001',  35, DATE_ADD(CURDATE(), INTERVAL 90 DAY),  DATE_SUB(CURDATE(), INTERVAL 45 DAY)),
    -- Frios
    (6, 2, 'FRI001-" . date('Ymd') . "-001',  50, DATE_ADD(CURDATE(), INTERVAL  6 DAY),  DATE_SUB(CURDATE(), INTERVAL  4 DAY)),
    (7, 2, 'FRI002-" . date('Ymd') . "-001',  30, DATE_ADD(CURDATE(), INTERVAL  8 DAY),  DATE_SUB(CURDATE(), INTERVAL  3 DAY)),
    (8, 2, 'FRI003-" . date('Ymd') . "-001',  70, DATE_ADD(CURDATE(), INTERVAL 25 DAY),  DATE_SUB(CURDATE(), INTERVAL 10 DAY)),
    (9, 2, 'FRI004-" . date('Ymd') . "-001',  20, DATE_ADD(CURDATE(), INTERVAL  1 DAY),  DATE_SUB(CURDATE(), INTERVAL  1 DAY)),
    -- Padaria
    (10,4, 'PAD001-" . date('Ymd') . "-001', 100, DATE_ADD(CURDATE(), INTERVAL  3 DAY),  DATE_SUB(CURDATE(), INTERVAL  2 DAY)),
    (11,4, 'PAD002-" . date('Ymd') . "-001',  45, DATE_ADD(CURDATE(), INTERVAL 10 DAY),  DATE_SUB(CURDATE(), INTERVAL  5 DAY)),
    (12,4, 'PAD003-" . date('Ymd') . "-001',  25, DATE_ADD(CURDATE(), INTERVAL 20 DAY),  DATE_SUB(CURDATE(), INTERVAL  8 DAY)),
    (13,4, 'PAD004-" . date('Ymd') . "-001', 200, DATE_ADD(CURDATE(), INTERVAL  2 DAY),  DATE_SUB(CURDATE(), INTERVAL  1 DAY)),
    -- Bebidas
    (14,3, 'BEB001-" . date('Ymd') . "-001',  80, DATE_ADD(CURDATE(), INTERVAL  5 DAY),  DATE_SUB(CURDATE(), INTERVAL  3 DAY)),
    (15,3, 'BEB002-" . date('Ymd') . "-001', 150, DATE_ADD(CURDATE(), INTERVAL 180 DAY), DATE_SUB(CURDATE(), INTERVAL 60 DAY)),
    (16,3, 'BEB003-" . date('Ymd') . "-001', 300, DATE_ADD(CURDATE(), INTERVAL 365 DAY), DATE_SUB(CURDATE(), INTERVAL 90 DAY)),
    (17,3, 'BEB004-" . date('Ymd') . "-001',  60, DATE_ADD(CURDATE(), INTERVAL 15 DAY),  DATE_SUB(CURDATE(), INTERVAL  5 DAY)),
    -- Hortifruti
    (18,5, 'HOR001-" . date('Ymd') . "-001',  40, DATE_ADD(CURDATE(), INTERVAL  3 DAY),  DATE_SUB(CURDATE(), INTERVAL  1 DAY)),
    (19,5, 'HOR002-" . date('Ymd') . "-001',  35, DATE_ADD(CURDATE(), INTERVAL  5 DAY),  DATE_SUB(CURDATE(), INTERVAL  2 DAY)),
    (20,5, 'HOR003-" . date('Ymd') . "-001',  80, DATE_ADD(CURDATE(), INTERVAL  4 DAY),  DATE_SUB(CURDATE(), INTERVAL  2 DAY)),
    (21,5, 'HOR004-" . date('Ymd') . "-001', 120, DATE_ADD(CURDATE(), INTERVAL 10 DAY),  DATE_SUB(CURDATE(), INTERVAL  4 DAY)),
    -- Mercearia
    (22,6, 'MER001-" . date('Ymd') . "-001', 200, DATE_ADD(CURDATE(), INTERVAL 365 DAY), DATE_SUB(CURDATE(), INTERVAL 90 DAY)),
    (23,6, 'MER002-" . date('Ymd') . "-001', 150, DATE_ADD(CURDATE(), INTERVAL 300 DAY), DATE_SUB(CURDATE(), INTERVAL 60 DAY)),
    (24,6, 'MER003-" . date('Ymd') . "-001',  60, DATE_ADD(CURDATE(), INTERVAL 730 DAY), DATE_SUB(CURDATE(), INTERVAL 90 DAY)),
    -- Congelados
    (25,7, 'CON001-" . date('Ymd') . "-001',  50, DATE_ADD(CURDATE(), INTERVAL 60 DAY),  DATE_SUB(CURDATE(), INTERVAL 20 DAY)),
    (26,7, 'CON002-" . date('Ymd') . "-001',  80, DATE_ADD(CURDATE(), INTERVAL 45 DAY),  DATE_SUB(CURDATE(), INTERVAL 15 DAY)),
    (27,7, 'CON003-" . date('Ymd') . "-001',  40, DATE_ADD(CURDATE(), INTERVAL  9 DAY),  DATE_SUB(CURDATE(), INTERVAL  4 DAY))
");

// Busca IDs na ordem de inserção
$loteIds = $pdo->query("SELECT id FROM lotes ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);

// Mapa produto_id → lote_id (primeiro lote de cada produto)
// Os lotes seguem a mesma ordem da inserção acima
$loteDefs   = [1,1,2,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27];
$produtoLote = [];
foreach ($loteDefs as $idx => $prodId) {
    if (!isset($produtoLote[$prodId])) {
        $produtoLote[$prodId] = $loteIds[$idx];
    }
}

// ── 5. HISTÓRICO DE VENDAS — INSERT EM LOTE ───────────────
// 95 dias regressivos a partir de hoje, volume variando por dia da semana.
// Toda a geração usa PHP date() relativo a now() — nunca datas fixas.

$produtosPrecos = $pdo->query("SELECT id, preco_venda FROM produtos")->fetchAll(PDO::FETCH_KEY_PAIR);

$populares    = [1,2,3,10,13,14,15,16,18,22,23];
$menosPopular = [5,7,9,11,12,17,19,20,24,25,26,27];
$canais       = ['PDV','PDV','PDV','PDV','ECOMMERCE','APP'];
$vNum         = 1;
$hoje         = new DateTime();

$vendasRows = [];
$itensRows  = [];

for ($diasAtras = 95; $diasAtras >= 1; $diasAtras--) {
    $dataVenda = (clone $hoje)->modify("-{$diasAtras} days");
    $dow       = (int) $dataVenda->format('N'); // 1=seg … 7=dom
    $numVendas = ($dow >= 6) ? rand(18, 28) : rand(8, 16);

    for ($v = 0; $v < $numVendas; $v++) {
        $hora     = sprintf('%02d:%02d:%02d', rand(7,22), rand(0,59), rand(0,59));
        $dtStr    = $dataVenda->format('Y-m-d') . ' ' . $hora;
        $canal    = $canais[array_rand($canais)];
        $numItens = rand(1, 4);
        $numVenda = 'V' . str_pad((string)$vNum++, 6, '0', STR_PAD_LEFT);

        $selecionados = [$populares[array_rand($populares)]];
        $pool = array_merge($populares, $menosPopular);
        for ($i = 1; $i < $numItens; $i++) {
            $pick = $pool[array_rand($pool)];
            if (!in_array($pick, $selecionados, true)) $selecionados[] = $pick;
        }

        $totalVenda = 0.0;
        $cestaTmp   = [];
        foreach ($selecionados as $prodId) {
            $preco    = (float)($produtosPrecos[$prodId] ?? 5.00);
            $qtd      = rand(1, 3);
            $desconto = (rand(0,12) === 0) ? round($preco * 0.10, 2) : 0.00;
            $loteId   = $produtoLote[$prodId] ?? $loteIds[0];
            $totalVenda += round(($qtd * $preco) - $desconto, 2);
            $cestaTmp[] = [$loteId, $prodId, $qtd, $preco, $desconto];
        }

        $vendasRows[] = [$numVenda, $dtStr, $totalVenda, $canal];
        foreach ($cestaTmp as $item) {
            $itensRows[] = [$numVenda, $item[0], $item[1], $item[2], $item[3], $item[4]];
        }
    }
}

// INSERT em lote — vendas (chunks de 200)
$vendaNumToId = [];
foreach (array_chunk($vendasRows, 200) as $chunk) {
    $ph   = implode(',', array_fill(0, count($chunk), '(?,?,?,?)'));
    $vals = array_merge(...array_map(fn($r) => $r, $chunk));
    $pdo->prepare("INSERT INTO vendas (numero_venda,data_venda,total,canal) VALUES {$ph}")->execute($vals);

    $numeros = array_column($chunk, 0);
    $inPH    = implode(',', array_fill(0, count($numeros), '?'));
    $stmt    = $pdo->prepare("SELECT id, numero_venda FROM vendas WHERE numero_venda IN ({$inPH})");
    $stmt->execute($numeros);
    foreach ($stmt->fetchAll() as $row) {
        $vendaNumToId[$row['numero_venda']] = (int) $row['id'];
    }
}

// INSERT em lote — itens_venda (chunks de 300)
$itensComId = [];
foreach ($itensRows as $item) {
    $vid = $vendaNumToId[$item[0]] ?? null;
    if ($vid === null) continue;
    $itensComId[] = [$vid, $item[1], $item[2], $item[3], $item[4], $item[5]];
}

foreach (array_chunk($itensComId, 300) as $chunk) {
    $ph   = implode(',', array_fill(0, count($chunk), '(?,?,?,?,?,?)'));
    $vals = array_merge(...array_map(fn($r) => $r, $chunk));
    $pdo->prepare("INSERT INTO itens_venda (venda_id,lote_id,produto_id,quantidade,preco_unit,desconto) VALUES {$ph}")->execute($vals);
}

// ── 6. AFINIDADE ──────────────────────────────────────────
$pdo->exec("INSERT INTO afinidade_produtos (produto_origem_id,produto_parceiro_id,frequencia,confianca) VALUES
    (1,6,120,0.72),(1,10,98,0.61),(1,3,85,0.55),
    (2,10,110,0.68),(2,13,90,0.58),(2,11,75,0.48),
    (3,1,95,0.60),(3,22,80,0.52),(3,5,70,0.45),
    (6,1,115,0.69),(6,7,88,0.56),(6,10,72,0.46),
    (7,6,80,0.51),(7,8,65,0.42),(8,6,70,0.44),
    (10,2,100,0.63),(10,11,85,0.54),(10,14,78,0.50),
    (13,2,95,0.60),(13,14,70,0.45),(14,1,75,0.48),
    (14,10,68,0.43),(18,2,65,0.42),(18,14,60,0.38),
    (19,10,55,0.36),(20,23,50,0.32),(25,14,80,0.50),
    (26,14,72,0.46),(27,15,65,0.41)");

// ── 7. COMBOS — validades relativas a TODAY ────────────────
$combosData = [
    // [lote_idx, produto_parceiro_id, desconto%, preco, status, +dias_validade]
    [$loteIds[2],  10, 15.00,  9.05, 'ATIVO',    2],
    [$loteIds[3],  11, 10.00, 10.71, 'APROVADO', 12],
    [$loteIds[5],   1, 12.00, 24.64, 'ATIVO',     7],
    [$loteIds[7],   1, 10.00, 27.36, 'APROVADO',  6],
    [$loteIds[8],   7,  8.00, 16.56, 'PENDENTE',  8],
    [$loteIds[10],  6, 18.00, 19.98, 'ATIVO',     1],
    [$loteIds[11],  2, 20.00,  9.44, 'ATIVO',     3],
    [$loteIds[13],  2, 15.00,  4.76, 'APROVADO',  2],
    [$loteIds[15],  1, 10.00, 25.92, 'PENDENTE',  5],
    [$loteIds[18], 14, 20.00, 15.04, 'ATIVO',     3],
    [$loteIds[19], 10, 12.00, 14.61, 'PENDENTE',  5],
    [$loteIds[20], 23, 10.00, 13.32, 'PENDENTE',  4],
    [$loteIds[26],  3,  8.00, 25.39, 'APROVADO',  9],
];

$stmtC = $pdo->prepare("
    INSERT INTO combos
        (lote_id, produto_parceiro_id, desconto_combo, preco_combo, status, aprovado_por, aprovado_em, valido_ate)
    VALUES (?, ?, ?, ?, ?, ?, ?, DATE_ADD(CURDATE(), INTERVAL ? DAY))
");
foreach ($combosData as $c) {
    $apBy = in_array($c[4], ['APROVADO','ATIVO']) ? 'gerente' : null;
    $apEm = in_array($c[4], ['APROVADO','ATIVO'])
        ? (clone $hoje)->modify('-1 day')->format('Y-m-d H:i:s')
        : null;
    $stmtC->execute([$c[0], $c[1], $c[2], $c[3], $c[4], $apBy, $apEm, $c[5]]);
}

// ── 8. ALERTAS — criados com NOW() ────────────────────────
$alertasData = [
    [$loteIds[2],  'URGENTE', 'Iogurte Natural — vence em 2 dias! Acionar liquidação imediata.', 1],
    [$loteIds[10], 'URGENTE', 'Peito de Peru — vence amanhã! Combo ativo, prioridade máxima.',   1],
    [$loteIds[11], 'URGENTE', 'Pão de Forma Integral — vence em 3 dias. Combo ativado.',         1],
    [$loteIds[13], 'URGENTE', 'Pão Francês — vence em 2 dias. Liquidação em curso.',             0],
    [$loteIds[18], 'URGENTE', 'Morango — vence em 3 dias. Combo sugerido pendente.',             0],
    [$loteIds[5],  'CRITICO', 'Requeijão Cremoso — 7 dias restantes. Combo aprovado.',           1],
    [$loteIds[7],  'CRITICO', 'Presunto Fatiado — 6 dias. Combo aprovado para ativação.',        1],
    [$loteIds[8],  'CRITICO', 'Salame Milano — 8 dias. Aguardando aprovação do combo.',          0],
    [$loteIds[15], 'CRITICO', 'Suco de Laranja — 5 dias. Combo pendente de aprovação.',         0],
    [$loteIds[19], 'CRITICO', 'Tomate Cereja — 5 dias. Iniciar ações comerciais.',              0],
    [$loteIds[20], 'CRITICO', 'Alface Americana — 4 dias. Combo sugerido pendente.',            0],
    [$loteIds[3],  'ATENCAO', 'Iogurte Natural (lote 2) — 12 dias. Monitoramento ativo.',       1],
    [$loteIds[26], 'ATENCAO', 'Lasanha Bolonhesa — 9 dias. Combo aprovado.',                    1],
];

$stmtA = $pdo->prepare("
    INSERT INTO alertas (lote_id, tipo, mensagem, enviado, enviado_em, criado_em)
    VALUES (?, ?, ?, ?, ?, NOW())
");
foreach ($alertasData as $a) {
    $enviadoEm = $a[3]
        ? (clone $hoje)->modify('-1 hour')->format('Y-m-d H:i:s')
        : null;
    $stmtA->execute([$a[0], $a[1], $a[2], $a[3], $enviadoEm]);
}

// ── Resultado ─────────────────────────────────────────────
$totalVendas = (int) $pdo->query("SELECT COUNT(*) FROM vendas")->fetchColumn();
$totalItens  = (int) $pdo->query("SELECT COUNT(*) FROM itens_venda")->fetchColumn();
$dataRef     = date('d/m/Y');
?>
<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8">
<style>
  body{font-family:monospace;background:#0a0a0a;color:#f0f0f0;padding:2rem}
  h2{color:#22c55e}
  .sub{color:#888;font-size:.8rem;margin-bottom:1.5rem}
  table{border-collapse:collapse;margin:1rem 0;min-width:320px}
  td,th{border:1px solid #2a2a2a;padding:.5rem 1.2rem}
  th{background:#1e1e1e;color:#888;text-align:left}
  .num{color:#ff2d78;font-weight:700;text-align:right}
  a{color:#ff2d78;margin-right:1.2rem}
  .warn{color:#f59e0b;font-size:.75rem;margin-top:1.5rem;opacity:.7}
  .tag{display:inline-block;background:rgba(34,197,94,.1);color:#22c55e;border:1px solid rgba(34,197,94,.2);padding:.1rem .5rem;border-radius:4px;font-size:.75rem;margin-left:.5rem}
</style></head><body>
<h2>✅ Seed v2.2 concluído! <span class="tag">📅 <?= $dataRef ?></span></h2>
<p class="sub">Datas calculadas a partir de hoje — lotes e alertas sempre atualizados.</p>
<table>
  <tr><th>Tabela</th><th>Registros</th></tr>
<?php
foreach (['categorias','fornecedores','produtos','lotes','afinidade_produtos','combos','alertas'] as $t) {
    $n = $pdo->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn();
    echo "<tr><td>{$t}</td><td class='num'>{$n}</td></tr>\n";
}
?>
  <tr><td>vendas <span class="tag">95 dias</span></td><td class="num"><?= $totalVendas ?></td></tr>
  <tr><td>itens_venda</td><td class="num"><?= $totalItens ?></td></tr>
</table>
<p>
  <a href="../../../frontend/index.html">→ Dashboard</a>
  <a href="../../../frontend/pages/historico.html">→ Histórico de Vendas</a>
  <a href="../../../frontend/pages/lotes.html">→ Lotes</a>
</p>
<p class="warn">⚠ Remova <code>public/seed.php</code> antes de ir a produção.</p>
</body></html>