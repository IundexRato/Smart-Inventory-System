<?php
// app/Services/FefoService.php
// Responsabilidade: toda a lógica de negócio relacionada ao FEFO
// Não conhece HTTP, não conhece HTML — só regras de negócio puras

namespace App\Services;

use App\Models\Lote;
use App\Models\Combo;
use App\Models\Alerta;

class FefoService {
    private Lote   $loteModel;
    private Combo  $comboModel;
    private Alerta $alertaModel;

    public function __construct() {
        $this->loteModel   = new Lote();
        $this->comboModel  = new Combo();
        $this->alertaModel = new Alerta();
    }

    // Classifica status baseado nos dias até o vencimento
    public function classificarStatus(string $dataValidade): string {
        $dias = (int) (new \DateTime())->diff(new \DateTime($dataValidade))->days;
        $vencido = strtotime($dataValidade) < time();

        if ($vencido)    return 'VENCIDO';
        if ($dias <= 3)  return 'URGENTE';
        if ($dias <= 9)  return 'CRITICO';
        if ($dias <= 30) return 'ATENCAO';
        return 'SEGURO';
    }

    // Recalcula e atualiza status de todos os lotes ativos
    // Chamado pelo cron job diário
    public function recalcularTodos(): array {
        $lotes = $this->loteModel->all();
        $atualizados = 0;

        foreach ($lotes as $lote) {
            $novoStatus = $this->classificarStatus($lote['data_validade']);
            if ($novoStatus !== $lote['status_validade']) {
                $this->loteModel->update($lote['id'], ['status_validade' => $novoStatus]);
                $atualizados++;

                // Gera alerta se mudou para status crítico ou urgente
                if (in_array($novoStatus, ['CRITICO', 'URGENTE', 'ATENCAO'])) {
                    $this->gerarAlerta($lote['id'], $novoStatus);
                }
            }
        }

        return ['lotes_atualizados' => $atualizados];
    }

    // Gera alerta para um lote (evita duplicatas no mesmo dia)
    private function gerarAlerta(int $loteId, string $tipo): void {
        $jaExiste = $this->alertaModel->where([
            'lote_id' => $loteId,
            'tipo'    => $tipo,
        ]);

        // Verifica se já existe alerta do mesmo tipo hoje
        foreach ($jaExiste as $alerta) {
            if (date('Y-m-d', strtotime($alerta['criado_em'])) === date('Y-m-d')) {
                return; // Já gerou hoje
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
