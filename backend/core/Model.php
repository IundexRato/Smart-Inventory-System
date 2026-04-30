<?php
// core/Model.php
// Responsabilidade: métodos genéricos de acesso ao banco (CRUD base)
// Cada Model da pasta app/Models/ herda esta classe

namespace Core;

use PDO;

abstract class Model {
    protected PDO $db;
    protected string $table;          // definida em cada Model filho
    protected string $primaryKey = 'id';

    public function __construct() {
        $this->db = Database::getInstance();
    }

    // Busca todos os registros
    public function all(string $orderBy = 'id', string $direction = 'ASC'): array {
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $stmt = $this->db->query("SELECT * FROM `{$this->table}` ORDER BY `{$orderBy}` {$direction}");
        return $stmt->fetchAll();
    }

    // Busca por PK
    public function find(int $id): array|false {
        $stmt = $this->db->prepare("SELECT * FROM `{$this->table}` WHERE `{$this->primaryKey}` = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    // Busca com WHERE simples: ['coluna' => 'valor']
    public function where(array $conditions): array {
        $clauses = implode(' AND ', array_map(fn($k) => "`{$k}` = ?", array_keys($conditions)));
        $stmt = $this->db->prepare("SELECT * FROM `{$this->table}` WHERE {$clauses}");
        $stmt->execute(array_values($conditions));
        return $stmt->fetchAll();
    }

    // Insert genérico — retorna o ID inserido
    public function insert(array $data): int {
        $cols = implode(', ', array_map(fn($k) => "`{$k}`", array_keys($data)));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $stmt = $this->db->prepare("INSERT INTO `{$this->table}` ({$cols}) VALUES ({$placeholders})");
        $stmt->execute(array_values($data));
        return (int) $this->db->lastInsertId();
    }

    // Update por PK
    public function update(int $id, array $data): bool {
        $set = implode(', ', array_map(fn($k) => "`{$k}` = ?", array_keys($data)));
        $stmt = $this->db->prepare("UPDATE `{$this->table}` SET {$set} WHERE `{$this->primaryKey}` = ?");
        return $stmt->execute([...array_values($data), $id]);
    }

    // Delete por PK
    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM `{$this->table}` WHERE `{$this->primaryKey}` = ?");
        return $stmt->execute([$id]);
    }

    // Contagem total
    public function count(): int {
        return (int) $this->db->query("SELECT COUNT(*) FROM `{$this->table}`")->fetchColumn();
    }

    // Query customizada — usada pelos Models filhos para queries complexas
    protected function query(string $sql, array $params = []): array {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // Query que retorna uma única linha
    protected function queryOne(string $sql, array $params = []): array|false {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }

    // Query que retorna um único valor escalar
    protected function queryScalar(string $sql, array $params = []): mixed {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }
}
