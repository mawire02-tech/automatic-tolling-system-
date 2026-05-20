<?php
// app/models/LogModel.php

class LogModel extends BaseModel {

    protected string $table = 'system_logs';

    public function write(
        string $type, string $severity, ?int $userId, ?int $deviceId,
        string $action, string $description, string $ip = ''
    ): void {
        $this->db->execute(
            "INSERT INTO system_logs (log_type, severity, user_id, device_id, action, description, ip_address)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$type, $severity, $userId, $deviceId, $action, $description, $ip]
        );
    }

    public function getPaginated(
        string $type = '', string $severity = '',
        string $dateFrom = '', string $dateTo = '',
        int $page = 1, int $limit = 50
    ): array {
        $where  = ['1=1'];
        $params = [];
        if ($dateFrom && $dateTo) {
            $where[] = "DATE(sl.logged_at) BETWEEN ? AND ?";
            $params  = array_merge($params, [$dateFrom, $dateTo]);
        }
        if ($type)     { $where[] = "sl.log_type = ?"; $params[] = $type; }
        if ($severity) { $where[] = "sl.severity = ?"; $params[] = $severity; }
        $w      = implode(' AND ', $where);
        $offset = ($page - 1) * $limit;
        return [
            'data'  => $this->db->fetchAll(
                "SELECT sl.*, u.username, d.device_code
                 FROM system_logs sl
                 LEFT JOIN users u   ON sl.user_id   = u.id
                 LEFT JOIN devices d ON sl.device_id = d.id
                 WHERE {$w} ORDER BY sl.logged_at DESC LIMIT {$limit} OFFSET {$offset}",
                $params
            ),
            'total' => (int)$this->db->fetchOne(
                "SELECT COUNT(*) as c FROM system_logs sl WHERE {$w}", $params
            )['c'],
        ];
    }
}
