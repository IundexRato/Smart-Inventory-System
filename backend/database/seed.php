<?php
// seed.php — rode uma vez para popular o banco com dados de exemplo
// Acesse: http://localhost/smart_inventory_v2/backend/database/seed.php

$cfg = require __DIR__ . '/../config/database.php';
$dsn = "mysql:host={$cfg['host']};dbname={$cfg['name']};charset={$cfg['charset']}";

$pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
]);

// Desativa checks temporariamente
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

// Limpa tabelas
foreach (['alertas','combos','itens_venda','vendas','afinidade_produtos','lotes','produtos','fornecedores','categorias'] as $t) {
    $pdo->exec("TRUNCATE TABLE `$t`");
}
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

// Categorias
$pdo->exec("INSERT INTO categorias (nome) VALUES
    ('Laticínios'),('Frios e Embutidos'),('Padaria'),('Bebidas'),('Hortifruti')");

// Fornecedores
$pdo->exec("INSERT INTO fornecedores (razao_social, cnpj) VALUES
    ('Laticínios Sul Ltda','11.222.333/0001-44'),
    ('Frios Premium SA','55.666.777/0001-88'),
    ('Distribuidora Bebidas RS','99.000.111/0001-22')");

// Produtos
$pdo->exec("INSERT INTO produtos (categoria_id, sku, nome, unidade_medida, preco_custo, preco_venda) VALUES
    (1,'LAT-001','Queijo Mussarela 500g','UN', 12.00, 19.90),
    (1,'LAT-002','Iogurte Natural 170g','UN',  2.50,  4.90),
    (2,'FRI-001','Presunto Fatiado 200g','UN',  6.00, 10.50),
    (2,'FRI-002','Salame Milano 100g','UN',     5.00,  9.00),
    (3,'PAD-001','Pão de Forma Integral','UN',  3.50,  6.90),
    (3,'PAD-002','Croissant Manteiga 4un','UN', 4.00,  7.50),
    (4,'BEB-001','Suco de Laranja 1L','UN',     4.50,  8.90),
    (4,'BEB-002','Refrigerante Cola 2L','UN',   3.00,  7.00),
    (5,'HOR-001','Morango Bandeja 300g','UN',   5.00,  9.90),
    (5,'HOR-002','Tomate Cereja 200g','UN',     3.80,  7.50)");

// Lotes — datas variadas para testar todos os status
$hoje = new DateTime();
$lotes = [
    // produto_id, fornecedor_id, codigo, qtd, validade (dias a partir de hoje)
    [1, 1, 'L-2024-001', 50,  45],   // SEGURO
    [1, 1, 'L-2024-002', 20,  12],   // ATENCAO
    [2, 1, 'L-2024-003', 80,   2],   // URGENTE
    [3, 2, 'L-2024-004', 30,   6],   // CRITICO
    [4, 2, 'L-2024-005', 15,   8],   // CRITICO
    [5, 2, 'L-2024-006', 40,  20],   // ATENCAO
    [6, 2, 'L-2024-007', 25,  35],   // SEGURO
    [7, 3, 'L-2024-008', 60,   1],   // URGENTE
    [8, 3, 'L-2024-009', 70,  60],   // SEGURO
    [9, 1, 'L-2024-010', 35,   3],   // URGENTE
    [10,1, 'L-2024-011', 45,  15],   // ATENCAO
];

$stmtL = $pdo->prepare("INSERT INTO lotes (produto_id, fornecedor_id, codigo_lote, quantidade, data_validade, data_entrada)
    VALUES (?, ?, ?, ?, ?, CURRENT_DATE)");

foreach ($lotes as $l) {
    $val = (clone $hoje)->modify("+{$l[4]} days")->format('Y-m-d');
    $stmtL->execute([$l[0], $l[1], $l[2], $l[3], $val]);
}

// Afinidade
$pdo->exec("INSERT INTO afinidade_produtos (produto_origem_id, produto_parceiro_id, frequencia, confianca) VALUES
    (1,3,120,0.72),(1,5,98,0.61),(2,6,87,0.55),
    (3,1,115,0.69),(4,3,75,0.50),(5,1,90,0.58),
    (7,5,60,0.45),(9,6,55,0.42),(10,7,80,0.52)");

// Combos (baseados nos lotes críticos/urgentes)
$pdo->exec("INSERT INTO combos (lote_id, produto_parceiro_id, desconto_combo, preco_combo, status, valido_ate) VALUES
    (3, 6, 15.00, 10.40, 'ATIVO',    DATE_ADD(CURRENT_DATE, INTERVAL 2 DAY)),
    (4, 1, 10.00, 17.46, 'APROVADO', DATE_ADD(CURRENT_DATE, INTERVAL 6 DAY)),
    (5, 3, 12.00, 17.16, 'PENDENTE', DATE_ADD(CURRENT_DATE, INTERVAL 8 DAY)),
    (8, 5, 20.00, 11.84, 'ATIVO',    DATE_ADD(CURRENT_DATE, INTERVAL 1 DAY)),
    (10,4,  8.00, 17.12, 'PENDENTE', DATE_ADD(CURRENT_DATE, INTERVAL 3 DAY))");

// Alertas
$pdo->exec("INSERT INTO alertas (lote_id, tipo, mensagem, enviado) VALUES
    (3, 'URGENTE', 'Iogurte Natural — vence em 2 dias! Acionar liquidação.', 1),
    (4, 'CRITICO', 'Presunto Fatiado — 6 dias restantes. Combo ativado.', 1),
    (5, 'CRITICO', 'Salame Milano — 8 dias. Aguardando aprovação do combo.', 0),
    (8, 'URGENTE', 'Suco de Laranja — vence amanhã! Liquidação imediata.', 1),
    (10,'URGENTE', 'Morango — 3 dias. Combo sugerido pendente.', 0)");

echo "<h2 style='font-family:monospace;color:green'>✅ Seed executado com sucesso!</h2>";
echo "<p style='font-family:monospace'><a href='../../frontend/index.html'>→ Acessar o Dashboard</a></p>";
