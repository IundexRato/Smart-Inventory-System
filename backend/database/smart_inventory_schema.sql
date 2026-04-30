-- ============================================================
--  SMART INVENTORY SYSTEM — Schema MySQL
--  Versão: 1.0
--  Descrição: Framework de banco de dados para gestão FEFO,
--             Matriz de Alerta e Cesta de Afinidade
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS smart_inventory
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE smart_inventory;

-- ------------------------------------------------------------
-- 1. CATEGORIAS DE PRODUTO
-- ------------------------------------------------------------
CREATE TABLE categorias (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome          VARCHAR(100)  NOT NULL,
    descricao     TEXT,
    criado_em     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Categorias dos produtos';


-- ------------------------------------------------------------
-- 2. PRODUTOS
-- ------------------------------------------------------------
CREATE TABLE produtos (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    categoria_id    INT UNSIGNED  NOT NULL,
    sku             VARCHAR(50)   NOT NULL UNIQUE COMMENT 'Código interno / EAN',
    nome            VARCHAR(150)  NOT NULL,
    descricao       TEXT,
    unidade_medida  ENUM('UN','KG','LT','CX','PCT') NOT NULL DEFAULT 'UN',
    preco_custo     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    preco_venda     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    margem_lucro    DECIMAL(5,2)  GENERATED ALWAYS AS (
                        CASE WHEN preco_custo > 0
                             THEN ROUND(((preco_venda - preco_custo) / preco_custo) * 100, 2)
                             ELSE 0 END
                    ) STORED COMMENT 'Margem calculada automaticamente (%)',
    ativo           TINYINT(1)    NOT NULL DEFAULT 1,
    criado_em       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_produto_categoria FOREIGN KEY (categoria_id)
        REFERENCES categorias(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX idx_sku        (sku),
    INDEX idx_categoria  (categoria_id),
    INDEX idx_ativo      (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Cadastro master de produtos';


-- ------------------------------------------------------------
-- 3. FORNECEDORES
-- ------------------------------------------------------------
CREATE TABLE fornecedores (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    razao_social  VARCHAR(150) NOT NULL,
    cnpj          VARCHAR(18)  UNIQUE,
    contato       VARCHAR(100),
    email         VARCHAR(100),
    telefone      VARCHAR(20),
    ativo         TINYINT(1)   NOT NULL DEFAULT 1,
    criado_em     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Cadastro de fornecedores';


-- ------------------------------------------------------------
-- 4. LOTES (coração do FEFO)
-- ------------------------------------------------------------
CREATE TABLE lotes (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    produto_id       INT UNSIGNED  NOT NULL,
    fornecedor_id    INT UNSIGNED,
    codigo_lote      VARCHAR(80)   NOT NULL COMMENT 'Código do fabricante ou interno',
    quantidade       DECIMAL(10,3) NOT NULL DEFAULT 0,
    quantidade_reservada DECIMAL(10,3) NOT NULL DEFAULT 0 COMMENT 'Reservado para combos ativos',
    data_fabricacao  DATE,
    data_validade    DATE          NOT NULL,
    data_entrada     DATE          NOT NULL DEFAULT (CURRENT_DATE),

    -- Coluna de status gerada pela Matriz de Alerta (FEFO)
    -- Atualizada via trigger ou cron job
    status_validade  ENUM('SEGURO','ATENCAO','CRITICO','URGENTE','VENCIDO')
                     NOT NULL DEFAULT 'SEGURO'
                     COMMENT 'SEGURO>30d | ATENCAO 10-30d | CRITICO 4-9d | URGENTE <=3d | VENCIDO',

    criado_em        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_lote_produto    FOREIGN KEY (produto_id)    REFERENCES produtos(id)    ON DELETE RESTRICT,
    CONSTRAINT fk_lote_fornecedor FOREIGN KEY (fornecedor_id) REFERENCES fornecedores(id) ON DELETE SET NULL,

    INDEX idx_produto_validade (produto_id, data_validade),
    INDEX idx_status           (status_validade),
    INDEX idx_validade         (data_validade)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Lotes virtuais - base do FEFO';


-- ------------------------------------------------------------
-- 5. TRIGGER — Atualiza status_validade ao inserir/atualizar lote
-- ------------------------------------------------------------
DELIMITER $$

CREATE TRIGGER trg_lote_status_insert
BEFORE INSERT ON lotes
FOR EACH ROW
BEGIN
    DECLARE dias INT;
    SET dias = DATEDIFF(NEW.data_validade, CURRENT_DATE);
    SET NEW.status_validade = CASE
        WHEN dias < 0  THEN 'VENCIDO'
        WHEN dias <= 3 THEN 'URGENTE'
        WHEN dias <= 9 THEN 'CRITICO'
        WHEN dias <= 30 THEN 'ATENCAO'
        ELSE 'SEGURO'
    END;
END$$

CREATE TRIGGER trg_lote_status_update
BEFORE UPDATE ON lotes
FOR EACH ROW
BEGIN
    DECLARE dias INT;
    SET dias = DATEDIFF(NEW.data_validade, CURRENT_DATE);
    SET NEW.status_validade = CASE
        WHEN dias < 0  THEN 'VENCIDO'
        WHEN dias <= 3 THEN 'URGENTE'
        WHEN dias <= 9 THEN 'CRITICO'
        WHEN dias <= 30 THEN 'ATENCAO'
        ELSE 'SEGURO'
    END;
END$$

DELIMITER ;


-- ------------------------------------------------------------
-- 6. HISTÓRICO DE VENDAS (alimenta a Cesta de Afinidade)
-- ------------------------------------------------------------
CREATE TABLE vendas (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    numero_venda  VARCHAR(50)   NOT NULL COMMENT 'PDV / número do cupom',
    data_venda    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    total         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    canal         ENUM('PDV','ECOMMERCE','APP','OUTRO') NOT NULL DEFAULT 'PDV',
    criado_em     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_numero (numero_venda),
    INDEX idx_data   (data_venda)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Cabeçalho de vendas';

CREATE TABLE itens_venda (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    venda_id    BIGINT UNSIGNED  NOT NULL,
    lote_id     INT UNSIGNED     NOT NULL,
    produto_id  INT UNSIGNED     NOT NULL,
    quantidade  DECIMAL(10,3)    NOT NULL,
    preco_unit  DECIMAL(10,2)    NOT NULL,
    desconto    DECIMAL(10,2)    NOT NULL DEFAULT 0.00,
    total_item  DECIMAL(10,2)    GENERATED ALWAYS AS
                    (ROUND((quantidade * preco_unit) - desconto, 2)) STORED,
    CONSTRAINT fk_item_venda    FOREIGN KEY (venda_id)   REFERENCES vendas(id)   ON DELETE CASCADE,
    CONSTRAINT fk_item_lote     FOREIGN KEY (lote_id)    REFERENCES lotes(id)    ON DELETE RESTRICT,
    CONSTRAINT fk_item_produto  FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE RESTRICT,
    INDEX idx_venda   (venda_id),
    INDEX idx_produto (produto_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Itens de cada venda';


-- ------------------------------------------------------------
-- 7. AFINIDADE ENTRE PRODUTOS (Cesta de Afinidade)
--    Populada pelo cron job / script de análise (últimos 90 dias)
-- ------------------------------------------------------------
CREATE TABLE afinidade_produtos (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    produto_origem_id   INT UNSIGNED   NOT NULL COMMENT 'Produto principal (próximo do vencimento)',
    produto_parceiro_id INT UNSIGNED   NOT NULL COMMENT 'Produto sugerido para combo',
    frequencia          INT UNSIGNED   NOT NULL DEFAULT 0  COMMENT 'Qtd de vendas juntos nos últimos 90 dias',
    confianca           DECIMAL(5,4)   NOT NULL DEFAULT 0  COMMENT 'P(parceiro | origem) — 0 a 1',
    atualizado_em       DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_par UNIQUE (produto_origem_id, produto_parceiro_id),
    CONSTRAINT fk_af_origem   FOREIGN KEY (produto_origem_id)   REFERENCES produtos(id) ON DELETE CASCADE,
    CONSTRAINT fk_af_parceiro FOREIGN KEY (produto_parceiro_id) REFERENCES produtos(id) ON DELETE CASCADE,
    INDEX idx_origem     (produto_origem_id),
    INDEX idx_confianca  (produto_origem_id, confianca DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Matriz de afinidade — Cesta de Afinidade';


-- ------------------------------------------------------------
-- 8. COMBOS SUGERIDOS
-- ------------------------------------------------------------
CREATE TABLE combos (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lote_id             INT UNSIGNED   NOT NULL COMMENT 'Lote do produto em risco',
    produto_parceiro_id INT UNSIGNED   NOT NULL,
    desconto_combo      DECIMAL(5,2)   NOT NULL DEFAULT 0.00 COMMENT 'Desconto aplicado ao combo (%)',
    preco_combo         DECIMAL(10,2)  NOT NULL,
    status              ENUM('PENDENTE','APROVADO','ATIVO','ENCERRADO','REJEITADO')
                        NOT NULL DEFAULT 'PENDENTE',
    aprovado_por        VARCHAR(100),
    aprovado_em         DATETIME,
    valido_ate          DATE           NOT NULL COMMENT 'Geralmente = data_validade do lote',
    criado_em           DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em       DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_combo_lote     FOREIGN KEY (lote_id)             REFERENCES lotes(id)    ON DELETE CASCADE,
    CONSTRAINT fk_combo_parceiro FOREIGN KEY (produto_parceiro_id) REFERENCES produtos(id) ON DELETE RESTRICT,
    INDEX idx_status    (status),
    INDEX idx_lote      (lote_id),
    INDEX idx_valido_ate (valido_ate)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Combos estratégicos gerados pelo sistema';


-- ------------------------------------------------------------
-- 9. LOG DE ALERTAS
-- ------------------------------------------------------------
CREATE TABLE alertas (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lote_id      INT UNSIGNED  NOT NULL,
    tipo         ENUM('ATENCAO','CRITICO','URGENTE','VENCIDO') NOT NULL,
    mensagem     TEXT,
    enviado      TINYINT(1)    NOT NULL DEFAULT 0,
    enviado_em   DATETIME,
    criado_em    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_alerta_lote FOREIGN KEY (lote_id) REFERENCES lotes(id) ON DELETE CASCADE,
    INDEX idx_tipo    (tipo),
    INDEX idx_enviado (enviado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Log de alertas gerados pelo monitoramento diário';


-- ------------------------------------------------------------
-- 10. VIEW — Dashboard: lotes em risco com sugestão de combo
-- ------------------------------------------------------------
CREATE OR REPLACE VIEW vw_lotes_em_risco AS
SELECT
    l.id                          AS lote_id,
    p.sku,
    p.nome                        AS produto,
    l.codigo_lote,
    l.quantidade,
    l.data_validade,
    DATEDIFF(l.data_validade, CURRENT_DATE) AS dias_restantes,
    l.status_validade,
    c.id                          AS combo_id,
    c.status                      AS status_combo,
    pp.nome                       AS produto_parceiro,
    c.desconto_combo,
    c.preco_combo
FROM lotes l
JOIN produtos p ON p.id = l.produto_id
LEFT JOIN combos c ON c.lote_id = l.id AND c.status IN ('PENDENTE','APROVADO','ATIVO')
LEFT JOIN produtos pp ON pp.id = c.produto_parceiro_id
WHERE l.status_validade IN ('ATENCAO','CRITICO','URGENTE')
  AND l.quantidade > 0
ORDER BY dias_restantes ASC;


-- ------------------------------------------------------------
-- 11. VIEW — Métricas de performance de combos
-- ------------------------------------------------------------
CREATE OR REPLACE VIEW vw_performance_combos AS
SELECT
    c.id                                    AS combo_id,
    p_origem.nome                           AS produto_origem,
    p_parceiro.nome                         AS produto_parceiro,
    l.data_validade,
    c.desconto_combo,
    c.status,
    COUNT(iv.id)                            AS vendas_geradas,
    COALESCE(SUM(iv.total_item), 0)         AS receita_gerada,
    c.criado_em                             AS combo_criado_em,
    c.aprovado_em
FROM combos c
JOIN lotes    l         ON l.id  = c.lote_id
JOIN produtos p_origem  ON p_origem.id  = l.produto_id
JOIN produtos p_parceiro ON p_parceiro.id = c.produto_parceiro_id
LEFT JOIN itens_venda iv ON iv.produto_id = c.produto_parceiro_id
    AND iv.venda_id IN (
        SELECT v.id FROM vendas v WHERE v.data_venda BETWEEN c.aprovado_em AND c.valido_ate
    )
GROUP BY c.id, p_origem.nome, p_parceiro.nome, l.data_validade,
         c.desconto_combo, c.status, c.criado_em, c.aprovado_em;


SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
--  FIM DO SCHEMA
-- ============================================================
