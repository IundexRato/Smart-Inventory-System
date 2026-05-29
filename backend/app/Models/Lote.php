<?php
// app/Models/Lote.php
// Responsabilidade: queries relacionadas à tabela `lotes`
// Regras de negócio ficam no LoteService / FefoService

namespace App\Models;

use Core\Model;

class Lote extends Model {
    protected string $table = 'lotes';

    // Todos os lotes com dados do produto e fornecedor
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

    // Lotes filtrados por status
    public function byStatus(string $status): array {
        return $this->query("
            SELECT l.*,
                   p.nome AS produto_nome, p.sku,
                   c.nome AS categoria
            FROM lotes l
            JOIN produtos p   ON p.id = l.produto_id
            JOIN categorias c ON c.id = p.categoria_id
            WHERE l.status_validade = ?
            ORDER BY l.data_validade ASC
        ", [$status]);
    }

    // Lotes em risco (view do banco) com combo sugerido
    public function emRisco(): array {
        return $this->query("SELECT * FROM vw_lotes_em_risco");
    }

    // KPIs para o dashboard
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

    // Distribuição por status (para gráfico)
    public function distribuicao(): array {
        return $this->query("
            SELECT status_validade, COUNT(*) AS total
            FROM lotes
            WHERE quantidade > 0
            GROUP BY status_validade
        ");
    }

    public function nextCodigoLote(int $produtoId): string {
        // Padrão: xxxnnn-aaaammdd-nnn
        // xxx = prefixo da categoria, nnn = número do produto na categoria
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

        $total = (int) $this->queryScalar(
            "SELECT COUNT(*) FROM lotes WHERE produto_id = ? AND DATE(data_entrada) = CURDATE()",
            [$produtoId]
        );

        $numLote = str_pad((string)($total + 1), 3, '0', STR_PAD_LEFT);
        return "{$prefixo}{$seq}-{$data}-{$numLote}";
    }

    public function comboCount(int $id): int {
        return (int) $this->queryScalar("SELECT COUNT(*) FROM combos WHERE lote_id = ?", [$id]);
    }

    public function deleteAlertas(int $id): void {
        $this->execute("DELETE FROM alertas WHERE lote_id = ?", [$id]);
    }
}