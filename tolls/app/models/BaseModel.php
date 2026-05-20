<?php
// app/models/BaseModel.php

abstract class BaseModel {

    protected Database $db;
    protected string $table = '';
    protected string $primaryKey = 'id';

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function find(int $id): ?array {
        return $this->db->fetchOne(
            "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ? LIMIT 1",
            [$id]
        );
    }

    public function findAll(array $conditions = [], string $orderBy = 'id DESC', int $limit = 0, int $offset = 0): array {
        $where  = '1=1';
        $params = [];
        foreach ($conditions as $col => $val) {
            $where   .= " AND {$col} = ?";
            $params[] = $val;
        }
        $sql = "SELECT * FROM {$this->table} WHERE {$where} ORDER BY {$orderBy}";
        if ($limit > 0) $sql .= " LIMIT {$limit} OFFSET {$offset}";
        return $this->db->fetchAll($sql, $params);
    }

    public function count(array $conditions = []): int {
        $where  = '1=1';
        $params = [];
        foreach ($conditions as $col => $val) {
            $where   .= " AND {$col} = ?";
            $params[] = $val;
        }
        return (int)$this->db->fetchOne("SELECT COUNT(*) as c FROM {$this->table} WHERE {$where}", $params)['c'];
    }

    public function create(array $data): int {
        $cols   = implode(', ', array_keys($data));
        $places = implode(', ', array_fill(0, count($data), '?'));
        $this->db->execute(
            "INSERT INTO {$this->table} ({$cols}) VALUES ({$places})",
            array_values($data)
        );
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): int {
        $sets   = implode(' = ?, ', array_keys($data)) . ' = ?';
        $params = array_values($data);
        $params[] = $id;
        return $this->db->execute(
            "UPDATE {$this->table} SET {$sets} WHERE {$this->primaryKey} = ?",
            $params
        );
    }

    public function delete(int $id): int {
        return $this->db->execute(
            "DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?",
            [$id]
        );
    }

    public function rawQuery(string $sql, array $params = []): array {
        return $this->db->fetchAll($sql, $params);
    }

    public function rawOne(string $sql, array $params = []): ?array {
        return $this->db->fetchOne($sql, $params);
    }

    public function rawExec(string $sql, array $params = []): int {
        return $this->db->execute($sql, $params);
    }
}
