<?php
// app/Models/Alerta.php
namespace App\Models;
use Core\Model;

class Alerta extends Model {
    protected string $table = 'alertas';

    public function allWithDetails(?int $enviado = null): array {
        $where  = $enviado !== null ? "WHERE a.enviado = ?" : "WHERE 1=1";
        $params = $enviado !== null ? [$enviado] : [];
        return $this->query("
            SELECT a.*,
                   p.nome AS produto, p.sku,
                   l.data_validade,
                   l.quantidade,
                   DATEDIFF(l.data_validade, CURRENT_DATE) AS dias_rest
            FROM alertas a
            JOIN lotes    l ON l.id  = a.lote_id
            JOIN produtos p ON p.id  = l.produto_id
            {$where}
            ORDER BY a.tipo DESC, a.criado_em DESC
        ", $params);
    }

    public function marcarEnviado(int $id): bool {
        return $this->update($id, [
            'enviado'    => 1,
            'enviado_em' => date('Y-m-d H:i:s'),
        ]);
    }
}
