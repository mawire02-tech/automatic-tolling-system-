<?php
// app/models/VehicleModel.php

class VehicleModel extends BaseModel {

    protected string $table = 'vehicles';

    public function findByPlate(string $plate): ?array {
        return $this->db->fetchOne("SELECT * FROM vehicles WHERE plate_number = ? LIMIT 1", [$plate]);
    }

    public function findByRfid(string $uid): ?array {
        return $this->db->fetchOne(
            "SELECT v.*, u.wallet_balance, u.full_name, u.id AS uid, u.status AS ustatus
             FROM vehicles v
             JOIN users u ON v.user_id = u.id
             WHERE v.rfid_tag = ?
             LIMIT 1",
            [$uid]
        );
    }

    public function withOwner(int $id): ?array {
        return $this->db->fetchOne(
            "SELECT v.*, u.full_name, u.username, u.wallet_balance
             FROM vehicles v LEFT JOIN users u ON v.user_id = u.id
             WHERE v.id = ?",
            [$id]
        );
    }

    public function getPaginated(string $search = '', string $type = '', int $page = 1, int $limit = 20): array {
        $where  = ['1=1'];
        $params = [];
        if ($search) {
            $where[]  = "(v.plate_number LIKE ? OR u.full_name LIKE ?)";
            $params   = array_merge($params, ["%{$search}%", "%{$search}%"]);
        }
        if ($type) { $where[] = "v.vehicle_type = ?"; $params[] = $type; }
        $w      = implode(' AND ', $where);
        $offset = ($page - 1) * $limit;
        $sql    = "SELECT v.*, u.full_name, u.username
                   FROM vehicles v LEFT JOIN users u ON v.user_id = u.id
                   WHERE {$w} ORDER BY v.registered_at DESC LIMIT {$limit} OFFSET {$offset}";
        $cntSql = "SELECT COUNT(*) as c FROM vehicles v LEFT JOIN users u ON v.user_id = u.id WHERE {$w}";
        return [
            'data'  => $this->db->fetchAll($sql, $params),
            'total' => (int)$this->db->fetchOne($cntSql, $params)['c'],
        ];
    }

    public function getActiveUsers(): array {
        return $this->db->fetchAll(
            "SELECT id, full_name, username FROM users WHERE role='user' AND status='active' ORDER BY full_name"
        );
    }
}
