<?php
// app/Models/Combo.php
namespace App\Models;
use Core\Model;

class Combo extends Model {
    protected string $table = 'combos';

    public function allWithDetails(?string $status = null): array {
        $where  = $status ? "WHERE c.status = ?" : "WHERE 1=1";
        $params = $status ? [$status] : [];
        return $this->query("
            SELECT c.*,
                   lo.nome  AS produto_origem,
                   lo.sku,
                   lp.nome  AS produto_parceiro,
                   l.status_validade,
                   l.data_validade,
                   l.quantidade,
                   DATEDIFF(c.valido_ate, CURRENT_DATE) AS dias_validade
            FROM combos c
            JOIN lotes    l  ON l.id  = c.lote_id
            JOIN produtos lo ON lo.id = l.produto_id
            JOIN produtos lp ON lp.id = c.produto_parceiro_id
            {$where}
            ORDER BY dias_validade ASC
        ", $params);
    }

    public function aprovar(int $id, string $aprovadoPor): bool {
        return $this->update($id, [
            'status'       => 'APROVADO',
            'aprovado_por' => $aprovadoPor,
            'aprovado_em'  => date('Y-m-d H:i:s'),
        ]);
    }
}
