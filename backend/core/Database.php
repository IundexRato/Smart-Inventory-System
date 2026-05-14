<?php
// core/Database.php
// Responsabilidade: gerenciar a conexao PDO e preparar o banco automaticamente.

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
        $charset = preg_replace('/[^a-zA-Z0-9_]/', '', $cfg['charset']) ?: 'utf8mb4';

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

    private static function ensureSchema(PDO $pdo, string $database): void {
        if (!self::columnExists($pdo, $database, 'categorias', 'prefixo')) {
            $pdo->exec("ALTER TABLE categorias ADD COLUMN prefixo VARCHAR(5) NOT NULL DEFAULT 'PRD' AFTER nome");
            $pdo->exec("UPDATE categorias SET prefixo = CASE
                WHEN nome LIKE 'Latic%' THEN 'LAT'
                WHEN nome = 'Frios e Embutidos' THEN 'FRI'
                WHEN nome = 'Padaria' THEN 'PAD'
                WHEN nome = 'Bebidas' THEN 'BEB'
                WHEN nome = 'Hortifruti' THEN 'HOR'
                ELSE UPPER(LEFT(nome, 3))
            END");
        }

        if (!self::columnExists($pdo, $database, 'produtos', 'peso')) {
            $pdo->exec("ALTER TABLE produtos ADD COLUMN peso DECIMAL(10,3) NOT NULL DEFAULT 0.000 AFTER unidade_medida");
        }
    }

    private static function tableExists(PDO $pdo, string $database, string $table): bool {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = ?
              AND TABLE_NAME = ?
        ");
        $stmt->execute([$database, $table]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private static function columnExists(PDO $pdo, string $database, string $table, string $column): bool {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = ?
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
        ");
        $stmt->execute([$database, $table, $column]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private static function splitSqlFile(string $sql): array {
        $statements = [];
        $delimiter = ';';
        $buffer = '';

        foreach (preg_split('/\R/', $sql) as $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '--')) {
                continue;
            }

            if (preg_match('/^DELIMITER\s+(.+)$/i', $trimmed, $matches)) {
                $delimiter = $matches[1];
                continue;
            }

            $buffer .= $line . "\n";

            if (str_ends_with(rtrim($buffer), $delimiter)) {
                $statement = substr(rtrim($buffer), 0, -strlen($delimiter));
                $statement = trim($statement);

                if ($statement !== '') {
                    $statements[] = $statement;
                }

                $buffer = '';
            }
        }

        $tail = trim($buffer);
        if ($tail !== '') {
            $statements[] = $tail;
        }

        return $statements;
    }

    private static function shouldSkipSchemaStatement(string $statement): bool {
        return (bool) preg_match('/^(CREATE\s+DATABASE|USE)\b/i', ltrim($statement));
    }

    private static function quoteIdentifier(string $identifier): string {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    // Impede instanciacao externa.
    private function __construct() {}
    private function __clone() {}
}
