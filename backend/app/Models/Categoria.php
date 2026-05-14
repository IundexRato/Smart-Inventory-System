<?php
// app/Models/Categoria.php
namespace App\Models;

use Core\Model;

class Categoria extends Model {
    protected string $table = 'categorias';

    public function allWithCounts(): array {
        return $this->query("
            SELECT c.*, COUNT(p.id) AS total_produtos
            FROM categorias c
            LEFT JOIN produtos p ON p.categoria_id = c.id
            GROUP BY c.id
            ORDER BY c.nome
        ");
    }

    public function findByNome(string $nome, ?int $exceptId = null): array|false {
        $sql = "SELECT * FROM categorias WHERE nome = ?";
        $params = [$nome];

        if ($exceptId !== null) {
            $sql .= " AND id != ?";
            $params[] = $exceptId;
        }

        return $this->queryOne($sql, $params);
    }

    public function produtoCount(int $id): int {
        return (int) $this->queryScalar("SELECT COUNT(*) FROM produtos WHERE categoria_id = ?", [$id]);
    }
}
