<?php
// app/Models/Fornecedor.php
namespace App\Models;

use Core\Model;

class Fornecedor extends Model {
    protected string $table = 'fornecedores';

    public function allWithStatus(?int $ativo = null): array {
        $where  = $ativo !== null ? 'WHERE f.ativo = ?' : 'WHERE 1=1';
        $params = $ativo !== null ? [$ativo] : [];
        return $this->query("
            SELECT f.*,
                   CASE WHEN f.ativo = 1 THEN 'ATIVO' ELSE 'ENCERRADO' END AS status_label
            FROM fornecedores f
            {$where}
            ORDER BY f.razao_social ASC
        ", $params);
    }

    public function findByCnpj(string $cnpj, ?int $exceptId = null): array|false {
        $sql    = 'SELECT * FROM fornecedores WHERE cnpj = ?';
        $params = [$cnpj];
        if ($exceptId !== null) {
            $sql    .= ' AND id != ?';
            $params[] = $exceptId;
        }
        return $this->queryOne($sql, $params);
    }

    public function loteCount(int $id): int {
        return (int) $this->queryScalar('SELECT COUNT(*) FROM lotes WHERE fornecedor_id = ?', [$id]);
    }
}
