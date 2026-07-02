<?php
// app/Services/FefoService.php
// Responsabilidade: regras de negócio FEFO — classificação e alertas.
// O status_validade é definido pelo trigger MySQL na inserção/atualização.
// Este service é usado apenas para: recálculo manual via cron e geração de alertas.

namespace App\Services;

use App\Models\Lote;
use App\Models\Alerta;

class FefoService {
    private Lote   $loteModel;
    private Alerta $alertaModel;

    public function __construct() {
        $this->loteModel   = new Lote();
        $this->alertaModel = new Alerta();
    }

    /**
     * Classifica o status de validade com base nos dias restantes.
     * Usado no LoteController apenas quando o trigger não puder agir
     * (ex: import manual sem passar pelo banco).
     */
    public function classificarStatus(string $dataValidade): string {
        $ts     = strtotime($dataValidade);
        $hoje   = strtotime(date('Y-m-d'));
        $dias   = (int) round(($ts - $hoje) / 86400);

        if ($dias < 0)   return 'VENCIDO';
        if ($dias <= 3)  return 'URGENTE';
        if ($dias <= 9)  return 'CRITICO';
        if ($dias <= 30) return 'ATENCAO';
        return 'SEGURO';
    }

    /**
     * Recalcula status de todos os lotes e gera alertas para os que mudaram.
     * Deve ser chamado pelo cron job diário — não pelo fluxo normal de criação.
     * O trigger MySQL já garante o status correto no momento da inserção/update.
     */
    public function recalcularTodos(): array {
        $lotes       = $this->loteModel->all();
        $atualizados = 0;

        foreach ($lotes as $lote) {
            $novoStatus = $this->classificarStatus($lote['data_validade']);
            if ($novoStatus !== $lote['status_validade']) {
                $this->loteModel->update($lote['id'], ['status_validade' => $novoStatus]);
                $atualizados++;

                if (in_array($novoStatus, ['CRITICO', 'URGENTE', 'ATENCAO'])) {
                    $this->gerarAlerta($lote['id'], $novoStatus);
                }
            }
        }

        return ['lotes_atualizados' => $atualizados];
    }

    private function gerarAlerta(int $loteId, string $tipo): void {
        $jaExiste = $this->alertaModel->where([
            'lote_id' => $loteId,
            'tipo'    => $tipo,
        ]);

        foreach ($jaExiste as $alerta) {
            if (date('Y-m-d', strtotime($alerta['criado_em'])) === date('Y-m-d')) {
                return;
            }
        }

        $mensagens = [
            'URGENTE' => 'Produto vence em até 3 dias! Liquidação imediata recomendada.',
            'CRITICO' => 'Produto vence em até 9 dias. Iniciar combos estratégicos.',
            'ATENCAO' => 'Produto vence em até 30 dias. Monitoramento ativo.',
        ];

        $this->alertaModel->insert([
            'lote_id'  => $loteId,
            'tipo'     => $tipo,
            'mensagem' => $mensagens[$tipo] ?? '',
            'enviado'  => 0,
        ]);
    }
}