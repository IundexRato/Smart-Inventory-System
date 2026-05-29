<?php
// app/Models/Venda.php
namespace App\Models;

use Core\Model;

class Venda extends Model {
    protected string $table = 'vendas';

    /**
     * Histórico agregado por produto/dia.
     *
     * Filtros:
     *   data_ini / data_fim  → intervalo de datas (YYYY-MM-DD)
     *   mes / ano            → filtro por mês e ano
     *   produto_id           → produto específico
     *   order                → 'asc' | 'desc' (por qtd_vendida)
     *                          omitir = apenas ordena por data DESC
     *
     * Padrão sem filtros: últimos 90 dias.
     * Receita = (preco_venda - preco_custo) * qtd_vendida
     */
    public function historico(array $filtros = []): array {
        $where  = [];
        $params = [];

        if (!empty($filtros['data_ini'])) {
            $where[]  = 'DATE(v.data_venda) >= ?';
            $params[] = $filtros['data_ini'];
        }
        if (!empty($filtros['data_fim'])) {
            $where[]  = 'DATE(v.data_venda) <= ?';
            $params[] = $filtros['data_fim'];
        }
        if (!empty($filtros['ano'])) {
            $where[]  = 'YEAR(v.data_venda) = ?';
            $params[] = (int) $filtros['ano'];
        }
        if (!empty($filtros['mes'])) {
            $where[]  = 'MONTH(v.data_venda) = ?';
            $params[] = (int) $filtros['mes'];
        }

        // Padrão: 90 dias quando nenhum filtro de data informado
        if (empty($filtros['data_ini']) && empty($filtros['data_fim'])
            && empty($filtros['ano']) && empty($filtros['mes'])) {
            $where[] = 'v.data_venda >= DATE_SUB(NOW(), INTERVAL 90 DAY)';
        }

        if (!empty($filtros['produto_id'])) {
            $where[]  = 'p.id = ?';
            $params[] = (int) $filtros['produto_id'];
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // Ordenação por qtd só quando explicitamente solicitada
        $hasOrder = !empty($filtros['order'])
            && in_array(strtolower((string) $filtros['order']), ['asc', 'desc'], true);
        $dirQtd = $hasOrder
            ? (strtolower($filtros['order']) === 'asc' ? 'ASC' : 'DESC')
            : null;

        $orderBy = 'ORDER BY data_venda DESC'
            . ($dirQtd ? ", qtd_vendida {$dirQtd}" : '');

        return $this->query("
            SELECT
                DATE(v.data_venda)                                    AS data_venda,
                YEAR(v.data_venda)                                    AS ano,
                MONTH(v.data_venda)                                   AS mes,
                p.id                                                  AS produto_id,
                p.sku,
                p.nome                                                AS produto_nome,
                cat.nome                                              AS categoria,
                SUM(iv.quantidade)                                    AS qtd_vendida,
                COUNT(DISTINCT v.id)                                  AS num_vendas,
                SUM((p.preco_venda - p.preco_custo) * iv.quantidade)  AS receita,
                AVG(iv.preco_unit)                                    AS preco_medio
            FROM itens_venda iv
            JOIN vendas     v   ON v.id   = iv.venda_id
            JOIN produtos   p   ON p.id   = iv.produto_id
            JOIN categorias cat ON cat.id = p.categoria_id
            {$whereClause}
            GROUP BY DATE(v.data_venda), p.id
            {$orderBy}
        ", $params);
    }

    /** Resumo mensal para gráfico */
    public function resumoMensal(int $meses = 12): array {
        return $this->query("
            SELECT
                YEAR(v.data_venda)  AS ano,
                MONTH(v.data_venda) AS mes,
                COUNT(DISTINCT v.id)                                     AS num_vendas,
                SUM((p.preco_venda - p.preco_custo) * iv.quantidade)     AS receita_total,
                SUM(iv.quantidade)                                       AS qtd_total
            FROM itens_venda iv
            JOIN vendas   v ON v.id = iv.venda_id
            JOIN produtos p ON p.id = iv.produto_id
            WHERE v.data_venda >= DATE_SUB(NOW(), INTERVAL ? MONTH)
            GROUP BY YEAR(v.data_venda), MONTH(v.data_venda)
            ORDER BY ano DESC, mes DESC
        ", [$meses]);
    }

    /** Produtos mais vendidos no período */
    public function topProdutos(int $limite = 10, ?string $dataIni = null, ?string $dataFim = null): array {
        $where  = [];
        $params = [];

        if ($dataIni) { $where[] = 'DATE(v.data_venda) >= ?'; $params[] = $dataIni; }
        if ($dataFim) { $where[] = 'DATE(v.data_venda) <= ?'; $params[] = $dataFim; }
        if (!$dataIni && !$dataFim) {
            $where[] = 'v.data_venda >= DATE_SUB(NOW(), INTERVAL 90 DAY)';
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $params[]    = $limite;

        return $this->query("
            SELECT
                p.id                                                 AS produto_id,
                p.sku,
                p.nome                                               AS produto_nome,
                cat.nome                                             AS categoria,
                SUM(iv.quantidade)                                   AS qtd_vendida,
                COUNT(DISTINCT v.id)                                 AS num_vendas,
                SUM((p.preco_venda - p.preco_custo) * iv.quantidade) AS receita
            FROM itens_venda iv
            JOIN vendas     v   ON v.id   = iv.venda_id
            JOIN produtos   p   ON p.id   = iv.produto_id
            JOIN categorias cat ON cat.id = p.categoria_id
            {$whereClause}
            GROUP BY p.id
            ORDER BY qtd_vendida DESC
            LIMIT ?
        ", $params);
    }
}