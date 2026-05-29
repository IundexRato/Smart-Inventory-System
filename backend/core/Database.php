<?php
// core/Database.php
// Responsabilidade: gerenciar a conexao PDO e preparar o banco automaticamente.
// v2.0: extras_migration.sql incorporado; suporte a nome_responsavel e status em fornecedores.

namespace Core;

use PDO;
use PDOException;

class Database {
    private static ?PDO $instance = null;

    public static function getInstance(): PDO {
        if (self::$instance === null) {
            $cfg = require __DIR__ . '/../config/database.php';

            try {
                self::ensureDatabaseExists($cfg);

                $dsn = "mysql:host={$cfg['host']};dbname={$cfg['name']};charset={$cfg['charset']}";
                self::$instance = new PDO($dsn, $cfg['user'], $cfg['pass'], [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);

                self::installSchemaIfNeeded(self::$instance, $cfg['name']);
                self::ensureSchema(self::$instance, $cfg['name']);
            } catch (PDOException $e) {
                http_response_code(500);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => false,
                    'error'   => 'Falha na conexao: ' . $e->getMessage(),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }
        }

        return self::$instance;
    }

    private static function ensureDatabaseExists(array $cfg): void {
        $dsn = "mysql:host={$cfg['host']};charset={$cfg['charset']}";
        $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        $database = self::quoteIdentifier($cfg['name']);
        $charset  = preg_replace('/[^a-zA-Z0-9_]/', '', $cfg['charset']) ?: 'utf8mb4';
        $pdo->exec("CREATE DATABASE IF NOT EXISTS {$database} CHARACTER SET {$charset} COLLATE {$charset}_unicode_ci");
    }

    private static function installSchemaIfNeeded(PDO $pdo, string $database): void {
        if (self::tableExists($pdo, $database, 'categorias')) {
            return;
        }

        $schemaPath = __DIR__ . '/../database/smart_inventory_schema.sql';
        if (!is_file($schemaPath)) {
            throw new PDOException('Arquivo de schema nao encontrado: ' . $schemaPath);
        }

        foreach (self::splitSqlFile((string) file_get_contents($schemaPath)) as $statement) {
            if (self::shouldSkipSchemaStatement($statement)) {
                continue;
            }
            $pdo->exec($statement);
        }
    }

    /**
     * Aplica migrações incrementais em bancos existentes.
     * Cada bloco é idempotente — pode rodar múltiplas vezes sem erro.
     */
    private static function ensureSchema(PDO $pdo, string $database): void {
        // Colunas vindas do extras_migration.sql (agora incorporadas no schema principal)
        if (!self::columnExists($pdo, $database, 'categorias', 'prefixo')) {
            $pdo->exec("ALTER TABLE categorias ADD COLUMN prefixo VARCHAR(5) NOT NULL DEFAULT 'PRD' AFTER nome");
            $pdo->exec("UPDATE categorias SET prefixo = CASE
                WHEN nome LIKE 'Latic%'           THEN 'LAT'
                WHEN nome = 'Frios e Embutidos'   THEN 'FRI'
                WHEN nome = 'Padaria'              THEN 'PAD'
                WHEN nome = 'Bebidas'              THEN 'BEB'
                WHEN nome = 'Hortifruti'           THEN 'HOR'
                ELSE UPPER(LEFT(nome, 3))
            END");
        }

        if (!self::columnExists($pdo, $database, 'produtos', 'peso')) {
            $pdo->exec("ALTER TABLE produtos ADD COLUMN peso DECIMAL(10,3) NOT NULL DEFAULT 0.000 AFTER unidade_medida");
        }

        // Novas colunas v2.0 — fornecedores
        if (!self::columnExists($pdo, $database, 'fornecedores', 'nome_responsavel')) {
            $pdo->exec("ALTER TABLE fornecedores ADD COLUMN nome_responsavel VARCHAR(100) NULL COMMENT 'Nome do responsavel pelo fornecedor' AFTER telefone");
        }

        if (!self::columnExists($pdo, $database, 'fornecedores', 'ativo')) {
            $pdo->exec("ALTER TABLE fornecedores ADD COLUMN ativo TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=ATIVO, 0=ENCERRADO' AFTER nome_responsavel");
        }

        // Garantir tabelas de vendas (podem não existir em bancos antigos)
        if (!self::tableExists($pdo, $database, 'vendas')) {
            $pdo->exec("CREATE TABLE vendas (
                id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                numero_venda  VARCHAR(50)   NOT NULL,
                data_venda    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
                total         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                canal         ENUM('PDV','ECOMMERCE','APP','OUTRO') NOT NULL DEFAULT 'PDV',
                criado_em     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_numero    (numero_venda),
                INDEX idx_data      (data_venda),
                INDEX idx_data_canal (data_venda, canal)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }

        if (!self::tableExists($pdo, $database, 'itens_venda')) {
            $pdo->exec("CREATE TABLE itens_venda (
                id          BIGINT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
                venda_id    BIGINT UNSIGNED  NOT NULL,
                lote_id     INT UNSIGNED     NOT NULL,
                produto_id  INT UNSIGNED     NOT NULL,
                quantidade  DECIMAL(10,3)    NOT NULL,
                preco_unit  DECIMAL(10,2)    NOT NULL,
                desconto    DECIMAL(10,2)    NOT NULL DEFAULT 0.00,
                total_item  DECIMAL(10,2)    GENERATED ALWAYS AS (ROUND((quantidade * preco_unit) - desconto, 2)) STORED,
                CONSTRAINT fk_iv_venda   FOREIGN KEY (venda_id)   REFERENCES vendas(id)   ON DELETE CASCADE,
                CONSTRAINT fk_iv_lote    FOREIGN KEY (lote_id)    REFERENCES lotes(id)    ON DELETE RESTRICT,
                CONSTRAINT fk_iv_produto FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE RESTRICT,
                INDEX idx_venda   (venda_id),
                INDEX idx_produto (produto_id),
                INDEX idx_lote    (lote_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }

        // Recriar view de histórico (idempotente com CREATE OR REPLACE)
        $pdo->exec("CREATE OR REPLACE VIEW vw_historico_vendas AS
            SELECT
                DATE(v.data_venda)   AS data_venda,
                YEAR(v.data_venda)   AS ano,
                MONTH(v.data_venda)  AS mes,
                p.id                 AS produto_id,
                p.sku,
                p.nome               AS produto_nome,
                cat.nome             AS categoria,
                SUM(iv.quantidade)   AS qtd_vendida,
                COUNT(DISTINCT v.id) AS num_vendas,
                SUM(iv.total_item)   AS receita,
                AVG(iv.preco_unit)   AS preco_medio
            FROM itens_venda iv
            JOIN vendas   v   ON v.id  = iv.venda_id
            JOIN produtos p   ON p.id  = iv.produto_id
            JOIN categorias cat ON cat.id = p.categoria_id
            GROUP BY DATE(v.data_venda), p.id
            ORDER BY data_venda DESC, qtd_vendida DESC");
    }

    private static function tableExists(PDO $pdo, string $database, string $table): bool {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?");
        $stmt->execute([$database, $table]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private static function columnExists(PDO $pdo, string $database, string $table, string $column): bool {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $stmt->execute([$database, $table, $column]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private static function splitSqlFile(string $sql): array {
        $statements = [];
        $delimiter  = ';';
        $buffer     = '';

        foreach (preg_split('/\R/', $sql) as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '--')) continue;

            if (preg_match('/^DELIMITER\s+(.+)$/i', $trimmed, $matches)) {
                $delimiter = $matches[1];
                continue;
            }

            $buffer .= $line . "\n";

            if (str_ends_with(rtrim($buffer), $delimiter)) {
                $statement = substr(rtrim($buffer), 0, -strlen($delimiter));
                $statement = trim($statement);
                if ($statement !== '') $statements[] = $statement;
                $buffer = '';
            }
        }

        $tail = trim($buffer);
        if ($tail !== '') $statements[] = $tail;

        return $statements;
    }

    private static function shouldSkipSchemaStatement(string $statement): bool {
        return (bool) preg_match('/^(CREATE\s+DATABASE|USE)\b/i', ltrim($statement));
    }

    private static function quoteIdentifier(string $identifier): string {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    private function __construct() {}
    private function __clone() {}
}