<?php
// app/Services/AfinidadeService.php
// Responsabilidade: calcular afinidade entre produtos e sugerir combos
// Baseado em frequência de co-ocorrência nos últimos 90 dias

namespace App\Services;

use Core\Database;
use App\Models\Combo;
use App\Models\Lote;

class AfinidadeService {
    private \PDO  $db;
    private Combo $comboModel;
    private Lote  $loteModel;

    public function __construct() {
        $this->db         = Database::getInstance();
        $this->comboModel = new Combo();
        $this->loteModel  = new Lote();
    }

    // Recalcula toda a matriz de afinidade (últimos 90 dias)
    // Chamado pelo cron job semanal
    public function recalcularMatriz(): array {
        // Busca todos os pares de produtos comprados juntos
        $pares = $this->db->query("
            SELECT
                a.produto_id AS origem,
                b.produto_id AS parceiro,
                COUNT(*)     AS frequencia
            FROM itens_venda a
            JOIN itens_venda b
                ON a.venda_id = b.venda_id
                AND a.produto_id <> b.produto_id
            JOIN vendas v ON v.id = a.venda_id
            WHERE v.data_venda >= DATE_SUB(NOW(), INTERVAL 90 DAY)
            GROUP BY a.produto_id, b.produto_id
            HAVING COUNT(*) >= 3
            ORDER BY frequencia DESC
        ")->fetchAll();

        // Conta total de vendas por produto (denominador da confiança)
        $totais = $this->db->query("
            SELECT produto_id, COUNT(DISTINCT venda_id) AS total
            FROM itens_venda
            JOIN vendas v ON v.id = venda_id
            WHERE v.data_venda >= DATE_SUB(NOW(), INTERVAL 90 DAY)
            GROUP BY produto_id
        ")->fetchAll(\PDO::FETCH_KEY_PAIR);

        $inseridos = 0;
        foreach ($pares as $par) {
            $totalOrigem = $totais[$par['origem']] ?? 1;
            $confianca   = round($par['frequencia'] / $totalOrigem, 4);

            // Upsert na tabela de afinidade
            $this->db->prepare("
                INSERT INTO afinidade_produtos
                    (produto_origem_id, produto_parceiro_id, frequencia, confianca)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    frequencia    = VALUES(frequencia),
                    confianca     = VALUES(confianca),
                    atualizado_em = NOW()
            ")->execute([$par['origem'], $par['parceiro'], $par['frequencia'], $confianca]);

            $inseridos++;
        }

        return ['pares_atualizados' => $inseridos];
    }

    // Sugere o melhor parceiro de combo para um produto em risco
    public function sugerirParceiro(int $produtoId): array|false {
        return $this->db->prepare("
            SELECT ap.produto_parceiro_id, ap.frequencia, ap.confianca,
                   p.nome, p.preco_venda, p.margem_lucro
            FROM afinidade_produtos ap
            JOIN produtos p ON p.id = ap.produto_parceiro_id
            WHERE ap.produto_origem_id = ?
            ORDER BY ap.confianca DESC, p.margem_lucro DESC
            LIMIT 1
        ")->execute([$produtoId]) ? $this->db->query("
            SELECT ap.produto_parceiro_id, ap.frequencia, ap.confianca,
                   p.nome, p.preco_venda, p.margem_lucro
            FROM afinidade_produtos ap
            JOIN produtos p ON p.id = ap.produto_parceiro_id
            WHERE ap.produto_origem_id = {$produtoId}
            ORDER BY ap.confianca DESC, p.margem_lucro DESC
            LIMIT 1
        ")->fetch() : false;
    }

    // Gera combos automaticamente para todos os lotes críticos/urgentes sem combo
    public function gerarCombosAutomaticos(): array {
        $lotesSemCombo = $this->db->query("
            SELECT l.id AS lote_id, l.produto_id, l.data_validade,
                   p.preco_venda
            FROM lotes l
            JOIN produtos p ON p.id = l.produto_id
            WHERE l.status_validade IN ('CRITICO','URGENTE')
              AND l.quantidade > 0
              AND l.id NOT IN (
                  SELECT lote_id FROM combos
                  WHERE status IN ('PENDENTE','APROVADO','ATIVO')
              )
        ")->fetchAll();

        $gerados = 0;
        foreach ($lotesSemCombo as $lote) {
            $parceiro = $this->sugerirParceiro($lote['produto_id']);
            if (!$parceiro) continue;

            $desconto   = 10.00; // desconto padrão inicial
            $precoCombo = round(
                ($lote['preco_venda'] + $parceiro['preco_venda']) * (1 - $desconto / 100),
                2
            );

            $this->comboModel->insert([
                'lote_id'             => $lote['lote_id'],
                'produto_parceiro_id' => $parceiro['produto_parceiro_id'],
                'desconto_combo'      => $desconto,
                'preco_combo'         => $precoCombo,
                'status'              => 'PENDENTE',
                'valido_ate'          => $lote['data_validade'],
            ]);
            $gerados++;
        }

        return ['combos_gerados' => $gerados];
    }
}
