<?php
// app/models/TransactionModel.php

class TransactionModel extends BaseModel {

    protected string $table = 'transactions';

    public function record(array $data): int {
        $data['transaction_ref'] = $data['transaction_ref'] ?? Security::generateRef('TXN');
        return $this->create($data);
    }

    public function getPaginated(
        string $search = '', string $dateFrom = '', string $dateTo = '',
        string $status = '', int $deviceId = 0, int $userId = 0,
        int $page = 1, int $limit = 20
    ): array {
        $where  = ['1=1'];
        $params = [];

        if ($dateFrom && $dateTo) {
            $where[] = "DATE(t.processed_at) BETWEEN ? AND ?";
            $params  = array_merge($params, [$dateFrom, $dateTo]);
        }
        if ($search) {
            $where[]  = "(v.plate_number LIKE ? OR t.transaction_ref LIKE ? OR u.full_name LIKE ?)";
            $params   = array_merge($params, ["%{$search}%", "%{$search}%", "%{$search}%"]);
        }
        if ($status)   { $where[] = "t.status = ?";    $params[] = $status; }
        if ($deviceId) { $where[] = "t.device_id = ?"; $params[] = $deviceId; }
        if ($userId)   { $where[] = "t.user_id = ?";   $params[] = $userId; }

        $w      = implode(' AND ', $where);
        $offset = ($page - 1) * $limit;

        $sql = "SELECT t.*, v.plate_number, u.full_name, d.device_name
                FROM transactions t
                LEFT JOIN vehicles v ON t.vehicle_id = v.id
                LEFT JOIN users u    ON t.user_id    = u.id
                LEFT JOIN devices d  ON t.device_id  = d.id
                WHERE {$w} ORDER BY t.processed_at DESC LIMIT {$limit} OFFSET {$offset}";

        $cnt = "SELECT COUNT(*) as c, COALESCE(SUM(CASE WHEN t.status='success' THEN t.toll_amount ELSE 0 END),0) as revenue
                FROM transactions t
                LEFT JOIN vehicles v ON t.vehicle_id = v.id
                LEFT JOIN users u    ON t.user_id    = u.id
                WHERE {$w}";

        $agg = $this->db->fetchOne($cnt, $params);
        return [
            'data'    => $this->db->fetchAll($sql, $params),
            'total'   => (int)$agg['c'],
            'revenue' => (float)$agg['revenue'],
        ];
    }

    public function getDailySummary(string $dateFrom, string $dateTo): array {
        return $this->db->fetchAll(
            "SELECT
               DATE(processed_at) as date,
               COALESCE(SUM(CASE WHEN status='success' THEN toll_amount ELSE 0 END),0) as revenue,
               COUNT(CASE WHEN status='success' THEN 1 END) as success_count,
               COUNT(CASE WHEN status='denied'  THEN 1 END) as denied_count,
               COUNT(*) as total_count
             FROM transactions
             WHERE DATE(processed_at) BETWEEN ? AND ?
             GROUP BY DATE(processed_at)
             ORDER BY date ASC",
            [$dateFrom, $dateTo]
        );
    }

    public function getDenyBreakdown(string $dateFrom, string $dateTo): array {
        return $this->db->fetchAll(
            "SELECT
               COALESCE(deny_reason, 'SUCCESS') as reason,
               COUNT(*) as count
             FROM transactions
             WHERE DATE(processed_at) BETWEEN ? AND ?
             GROUP BY deny_reason
             ORDER BY count DESC",
            [$dateFrom, $dateTo]
        );
    }

    public function getVehicleClassStats(string $dateFrom, string $dateTo): array {
        return $this->db->fetchAll(
            "SELECT vehicle_type,
               COUNT(CASE WHEN status='success' THEN 1 END) as success_count,
               COALESCE(SUM(CASE WHEN status='success' THEN toll_amount ELSE 0 END),0) as revenue
             FROM transactions
             WHERE DATE(processed_at) BETWEEN ? AND ?
             GROUP BY vehicle_type",
            [$dateFrom, $dateTo]
        );
    }

    public function getGatePerformance(string $dateFrom, string $dateTo): array {
        return $this->db->fetchAll(
            "SELECT d.device_name, d.device_code, d.status,
               COUNT(CASE WHEN t.status='success' THEN 1 END) as success_count,
               COUNT(CASE WHEN t.status='denied'  THEN 1 END) as denied_count,
               COALESCE(SUM(CASE WHEN t.status='success' THEN t.toll_amount ELSE 0 END),0) as revenue
             FROM devices d
             LEFT JOIN transactions t ON d.id = t.device_id
               AND DATE(t.processed_at) BETWEEN ? AND ?
             GROUP BY d.id
             ORDER BY revenue DESC",
            [$dateFrom, $dateTo]
        );
    }

    public function getHourlyStats(string $date): array {
        return $this->db->fetchAll(
            "SELECT HOUR(processed_at) as hour,
               COUNT(CASE WHEN status='success' THEN 1 END) as count,
               COALESCE(SUM(CASE WHEN status='success' THEN toll_amount ELSE 0 END),0) as revenue
             FROM transactions
             WHERE DATE(processed_at) = ?
             GROUP BY HOUR(processed_at)
             ORDER BY hour",
            [$date]
        );
    }
}
