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
}
