<?php
// app/models/UserModel.php

class UserModel extends BaseModel {

    protected string $table = 'users';

    public function findByUsernameOrEmail(string $identifier): ?array {
        return $this->db->fetchOne(
            "SELECT * FROM users WHERE (username = ? OR email = ?) LIMIT 1",
            [$identifier, $identifier]
        );
    }

    public function findByUsername(string $username): ?array {
        return $this->db->fetchOne("SELECT * FROM users WHERE username = ? LIMIT 1", [$username]);
    }

    public function findByEmail(string $email): ?array {
        return $this->db->fetchOne("SELECT * FROM users WHERE email = ? LIMIT 1", [$email]);
    }

    public function updateBalance(int $userId, float $amount): void {
        $this->db->execute(
            "UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?",
            [$amount, $userId]
        );
    }

    public function deductBalance(int $userId, float $amount): bool {
        $user = $this->find($userId);
        if (!$user || (float)$user['wallet_balance'] < $amount) return false;
        $this->db->execute(
            "UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ?",
            [$amount, $userId]
        );
        return true;
    }

    public function incrementLoginAttempts(int $id): void {
        $this->db->execute(
            "UPDATE users SET login_attempts = login_attempts + 1 WHERE id = ?",
            [$id]
        );
    }

    public function lockAccount(int $id, int $minutes = 15): void {
        $until = date('Y-m-d H:i:s', time() + $minutes * 60);
        $this->db->execute(
            "UPDATE users SET locked_until = ?, login_attempts = 0 WHERE id = ?",
            [$until, $id]
        );
    }

    public function resetLoginAttempts(int $id): void {
        $this->db->execute(
            "UPDATE users SET login_attempts = 0, locked_until = NULL, last_login = NOW() WHERE id = ?",
            [$id]
        );
    }

    public function getPaginated(string $search = '', string $role = '', string $status = '', int $page = 1, int $limit = 20): array {
        $where  = ['1=1'];
        $params = [];

        if ($search) {
            $where[] = "(u.username LIKE ? OR u.email LIKE ? OR u.full_name LIKE ?)";
            $params  = array_merge($params, ["%{$search}%", "%{$search}%", "%{$search}%"]);
        }

        if ($role) {
            $where[] = "u.role = ?";
            $params[] = $role;
        }

        if ($status) {
            $where[] = "u.status = ?";
            $params[] = $status;
        }

        $w      = implode(' AND ', $where);
        $offset = ($page - 1) * $limit;

        return [
            'data' => $this->db->fetchAll(
                "SELECT u.*, d.device_name 
                 FROM users u 
                 LEFT JOIN devices d ON u.assigned_device_id = d.id 
                 WHERE {$w} 
                 ORDER BY u.created_at DESC 
                 LIMIT {$limit} OFFSET {$offset}",
                $params
            ),

            'total' => (int)$this->db->fetchOne(
                "SELECT COUNT(*) as c 
                 FROM users u 
                 WHERE {$w}",
                $params
            )['c'],
        ];
    }
}