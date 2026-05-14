-- Migra banco existente para as funcionalidades que estavam na pasta extra.
-- Execute apenas se seu banco ja foi criado antes desta atualizacao.

ALTER TABLE categorias
    ADD COLUMN prefixo VARCHAR(5) NOT NULL DEFAULT 'PRD' AFTER nome;

ALTER TABLE produtos
    ADD COLUMN peso DECIMAL(10,3) NOT NULL DEFAULT 0.000 AFTER unidade_medida;

UPDATE categorias SET prefixo = 'LAT' WHERE nome = 'Laticínios';
UPDATE categorias SET prefixo = 'FRI' WHERE nome = 'Frios e Embutidos';
UPDATE categorias SET prefixo = 'PAD' WHERE nome = 'Padaria';
UPDATE categorias SET prefixo = 'BEB' WHERE nome = 'Bebidas';
UPDATE categorias SET prefixo = 'HOR' WHERE nome = 'Hortifruti';
UPDATE categorias SET prefixo = UPPER(LEFT(nome, 3)) WHERE prefixo IS NULL OR prefixo = '';
