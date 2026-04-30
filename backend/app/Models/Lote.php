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
}
