<?php
// app/Models/Saida.php
namespace App\Models;
use Core\Model;

class Saida extends Model {
    protected string $table = 'saidas_estoque';

    public function allWithDetails(): array {
        return $this->query("
            SELECT s.*,
                   l.codigo_lote,
                   p.nome AS produto_nome,
                   p.sku
            FROM saidas_estoque s
            JOIN lotes    l ON l.id = s.lote_id
            JOIN produtos p ON p.id = l.produto_id
            ORDER BY s.criado_em DESC
        ");
    }

    public function byLote(int $loteId): array {
        return $this->query(
            "SELECT * FROM saidas_estoque WHERE lote_id = ? ORDER BY criado_em DESC",
            [$loteId]
        );
    }

    public function totalSaidoPorLote(int $loteId): float {
        return (float) $this->queryScalar(
            "SELECT COALESCE(SUM(quantidade), 0) FROM saidas_estoque WHERE lote_id = ?",
            [$loteId]
        );
    }
}