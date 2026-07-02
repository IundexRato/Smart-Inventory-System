<?php
// app/Models/Lote.php
namespace App\Models;
use Core\Model;

class Lote extends Model {
    protected string $table = 'lotes';

    public function allWithDetails(): array {
        return $this->query("
            SELECT l.*,
                   p.nome AS produto_nome, p.sku,
                   c.nome AS categoria,
                   f.razao_social AS fornecedor
            FROM lotes l
            JOIN produtos p    ON p.id = l.produto_id
            JOIN categorias c  ON c.id = p.categoria_id
            LEFT JOIN fornecedores f ON f.id = l.fornecedor_id
            ORDER BY l.data_validade ASC
        ");
    }

    public function byStatus(string $status): array {
        return $this->query("
            SELECT l.*,
                   p.nome AS produto_nome, p.sku,
                   c.nome AS categoria,
                   f.razao_social AS fornecedor
            FROM lotes l
            JOIN produtos p   ON p.id = l.produto_id
            JOIN categorias c ON c.id = p.categoria_id
            LEFT JOIN fornecedores f ON f.id = l.fornecedor_id
            WHERE l.status_validade = ?
            ORDER BY l.data_validade ASC
        ", [$status]);
    }

    public function emRisco(): array {
        return $this->query("SELECT * FROM vw_lotes_em_risco");
    }

    public function kpis(): array {
        return $this->queryOne("
            SELECT
                COUNT(*)                                                        AS total_lotes,
                COALESCE(SUM(quantidade), 0)                                   AS total_itens,
                SUM(CASE WHEN status_validade = 'URGENTE' THEN 1 ELSE 0 END)  AS urgente,
                SUM(CASE WHEN status_validade = 'CRITICO' THEN 1 ELSE 0 END)  AS critico,
                SUM(CASE WHEN status_validade = 'ATENCAO' THEN 1 ELSE 0 END)  AS atencao,
                SUM(CASE WHEN status_validade = 'SEGURO'  THEN 1 ELSE 0 END)  AS seguro
            FROM lotes
            WHERE quantidade > 0
        ");
    }

    public function distribuicao(): array {
        return $this->query("
            SELECT status_validade, COUNT(*) AS total
            FROM lotes
            WHERE quantidade > 0
            GROUP BY status_validade
        ");
    }

    public function nextCodigoLote(int $produtoId): string {
        $row = $this->queryOne("
            SELECT c.prefixo,
                   (SELECT COUNT(*) FROM produtos p2
                    WHERE p2.categoria_id = p.categoria_id AND p2.id <= p.id) AS seq
            FROM produtos p
            JOIN categorias c ON c.id = p.categoria_id
            WHERE p.id = ?
        ", [$produtoId]);

        $prefixo = strtoupper($row['prefixo'] ?? 'PRD');
        $seq     = str_pad((string)($row['seq'] ?? 1), 3, '0', STR_PAD_LEFT);
        $data    = date('Ymd');

        // Conta TODOS os lotes históricos do produto, não apenas os de hoje
        $totalHistorico = (int) $this->queryScalar(
            "SELECT COUNT(*) FROM lotes WHERE produto_id = ?",
            [$produtoId]
        );

        $numLote = str_pad((string)($totalHistorico + 1), 3, '0', STR_PAD_LEFT);
        return "{$prefixo}{$seq}-{$data}-{$numLote}";
    }

    public function comboCount(int $id): int {
        return (int) $this->queryScalar(
            "SELECT COUNT(*) FROM combos WHERE lote_id = ?", [$id]
        );
    }

    public function deleteAlertas(int $id): void {
        $this->execute("DELETE FROM alertas WHERE lote_id = ?", [$id]);
    }

    public function registrarSaida(int $loteId, float $quantidade): bool {
        return $this->execute(
            "UPDATE lotes SET quantidade = GREATEST(0, quantidade - ?) WHERE id = ?",
            [$quantidade, $loteId]
        );
    }
}