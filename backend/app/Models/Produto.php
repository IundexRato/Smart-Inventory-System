<?php
// app/Models/Produto.php
namespace App\Models;
use Core\Model;

class Produto extends Model {
    protected string $table = 'produtos';

    public function allWithDetails(): array {
        return $this->query("
            SELECT p.*, c.nome AS categoria,
                   COUNT(l.id)           AS total_lotes,
                   COALESCE(SUM(l.quantidade), 0) AS estoque_total
            FROM produtos p
            JOIN categorias c ON c.id = p.categoria_id
            LEFT JOIN lotes l ON l.produto_id = p.id
            GROUP BY p.id
            ORDER BY p.nome
        ");
    }

    public function afinidades(int $produtoId): array {
        return $this->query("
            SELECT ap.*, p.nome AS produto_parceiro_nome, p.sku
            FROM afinidade_produtos ap
            JOIN produtos p ON p.id = ap.produto_parceiro_id
            WHERE ap.produto_origem_id = ?
            ORDER BY ap.confianca DESC
        ", [$produtoId]);
    }

    public function findBySku(string $sku, ?int $exceptId = null): array|false {
        $sql = "SELECT * FROM produtos WHERE sku = ?";
        $params = [$sku];

        if ($exceptId !== null) {
            $sql .= " AND id != ?";
            $params[] = $exceptId;
        }

        return $this->queryOne($sql, $params);
    }

    public function nextSku(int $categoriaId): string {
        $categoria = $this->queryOne("SELECT prefixo FROM categorias WHERE id = ?", [$categoriaId]);
        $prefixo = $categoria['prefixo'] ?? 'PRD';
        $total = (int) $this->queryScalar("SELECT COUNT(*) FROM produtos WHERE categoria_id = ?", [$categoriaId]);

        return strtoupper($prefixo) . '-' . str_pad((string) ($total + 1), 3, '0', STR_PAD_LEFT);
    }

    public function loteCount(int $id): int {
        return (int) $this->queryScalar("SELECT COUNT(*) FROM lotes WHERE produto_id = ?", [$id]);
    }

    public function comboParceiroCount(int $id): int {
        return (int) $this->queryScalar("SELECT COUNT(*) FROM combos WHERE produto_parceiro_id = ?", [$id]);
    }

    public function deleteAfinidades(int $id): void {
        $this->execute(
            "DELETE FROM afinidade_produtos WHERE produto_origem_id = ? OR produto_parceiro_id = ?",
            [$id, $id]
        );
    }
}
