<?php
namespace App\Core;

use PDO;

abstract class Model {
    protected PDO $db;
    protected string $table;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function findById(int $id): ?array {
        $st = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = ?");
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    public function findAll(array $where = [], string $order = 'id ASC', int $limit = 0): array {
        $sql    = "SELECT * FROM {$this->table}";
        $params = [];
        if ($where) {
            $clauses = array_map(fn($k) => "$k = ?", array_keys($where));
            $sql    .= ' WHERE ' . implode(' AND ', $clauses);
            $params  = array_values($where);
        }
        $sql .= " ORDER BY $order";
        if ($limit > 0) $sql .= " LIMIT $limit";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    public function insert(array $data): int {
        $cols  = implode(', ', array_keys($data));
        $holds = implode(', ', array_fill(0, count($data), '?'));
        $st    = $this->db->prepare("INSERT INTO {$this->table} ($cols) VALUES ($holds)");
        $st->execute(array_values($data));
        return (int) $this->db->lastInsertId();
    }

    public function updateById(int $id, array $data): bool {
        $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($data)));
        $st  = $this->db->prepare("UPDATE {$this->table} SET $set WHERE id = ?");
        return $st->execute([...array_values($data), $id]);
    }

    public function deleteById(int $id): bool {
        $st = $this->db->prepare("DELETE FROM {$this->table} WHERE id = ?");
        return $st->execute([$id]);
    }

    protected function q(string $sql, array $params = []): array {
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    protected function qOne(string $sql, array $params = []): ?array {
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetch() ?: null;
    }

    protected function exec(string $sql, array $params = []): bool {
        $st = $this->db->prepare($sql);
        return $st->execute($params);
    }

    protected function execGetId(string $sql, array $params = []): int {
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return (int) $this->db->lastInsertId();
    }
}
