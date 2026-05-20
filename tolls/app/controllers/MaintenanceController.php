<?php
// app/controllers/MaintenanceController.php

class MaintenanceController {

    private $db;

    public function __construct() {
        Security::requireStrictAdmin();
        $this->db = Database::getInstance();
    }

    public function index(): void {
        // System stats
        $stats = array(
            'total_transactions' => (int)$this->db->fetchOne("SELECT COUNT(*) as c FROM transactions")['c'],
            'total_logs'         => (int)$this->db->fetchOne("SELECT COUNT(*) as c FROM system_logs")['c'],
            'total_notifications'=> (int)$this->db->fetchOne("SELECT COUNT(*) as c FROM notifications")['c'],
            'old_logs_30d'       => (int)$this->db->fetchOne("SELECT COUNT(*) as c FROM system_logs WHERE logged_at < DATE_SUB(NOW(), INTERVAL 30 DAY)")['c'],
            'old_notifs_30d'     => (int)$this->db->fetchOne("SELECT COUNT(*) as c FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)")['c'],
            'denied_tx'          => (int)$this->db->fetchOne("SELECT COUNT(*) as c FROM transactions WHERE status='denied'")['c'],
            'db_size_mb'         => $this->getDbSize(),
            'php_version'        => PHP_VERSION,
            'server_software'    => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'uptime'             => $this->getUptime(),
        );

        // Recent error logs
        $errorLogs = $this->db->fetchAll(
            "SELECT * FROM system_logs WHERE severity IN ('error','critical') ORDER BY logged_at DESC LIMIT 20"
        );

        // Table sizes
        $tableSizes = $this->db->fetchAll(
            "SELECT table_name, 
                    ROUND((data_length + index_length)/1024/1024, 2) as size_mb,
                    table_rows
             FROM information_schema.tables 
             WHERE table_schema = DATABASE()
             ORDER BY (data_length + index_length) DESC"
        );

        Response::view('admin/maintenance', compact('stats','errorLogs','tableSizes'), 'admin');
    }

    public function runAction(): void {
        if (!Security::verifyCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) Response::error('Invalid CSRF', 403);
        $action = Security::sanitize($_POST['action'] ?? '');

        switch ($action) {
            case 'clear_old_logs':
                $deleted = $this->db->execute(
                    "DELETE FROM system_logs WHERE logged_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
                );
                $this->log("Cleared {$deleted} old system logs (>30 days)");
                Response::json(array('success'=>true,'message'=>"{$deleted} old log(s) deleted successfully"));
                break;

            case 'clear_old_notifications':
                $deleted = $this->db->execute(
                    "DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY) AND is_read=1"
                );
                $this->log("Cleared {$deleted} old read notifications (>30 days)");
                Response::json(array('success'=>true,'message'=>"{$deleted} old notification(s) deleted"));
                break;

            case 'clear_denied_tx':
                $deleted = $this->db->execute(
                    "DELETE FROM transactions WHERE status='denied' AND processed_at < DATE_SUB(NOW(), INTERVAL 90 DAY)"
                );
                $this->log("Cleared {$deleted} old denied transactions (>90 days)");
                Response::json(array('success'=>true,'message'=>"{$deleted} denied transaction(s) deleted"));
                break;

            case 'clear_expired_commands':
                $deleted = $this->db->execute(
                    "DELETE FROM device_commands WHERE status IN ('executed','expired') AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)"
                );
                $this->log("Cleared {$deleted} executed/expired gate commands (>7 days)");
                Response::json(array('success'=>true,'message'=>"{$deleted} old gate command(s) deleted"));
                break;

            case 'reset_device_stats':
                $count = $this->db->execute("UPDATE devices SET total_transactions=0, total_revenue=0");
                $this->log("Reset statistics for {$count} device(s)");
                Response::json(array('success'=>true,'message'=>"Statistics reset for {$count} gate(s)"));
                break;

            case 'test_db':
                $start = microtime(true);
                $this->db->fetchOne("SELECT COUNT(*) as c FROM transactions");
                $ms = round((microtime(true) - $start) * 1000, 2);
                Response::json(array('success'=>true,'message'=>"Database responding normally ({$ms}ms)"));
                break;

            default:
                Response::json(array('error'=>'Unknown action'), 400);
        }
    }

    private function getDbSize(): float {
        $row = $this->db->fetchOne(
            "SELECT ROUND(SUM(data_length + index_length)/1024/1024, 2) as size FROM information_schema.tables WHERE table_schema=DATABASE()"
        );
        return (float)($row['size'] ?? 0);
    }

    private function getUptime(): string {
        if (PHP_OS_FAMILY === 'Windows') return 'N/A on Windows';
        $up = @shell_exec('uptime -p 2>/dev/null');
        return $up ? trim($up) : 'N/A';
    }

    private function log(string $action): void {
        $this->db->execute(
            "INSERT INTO system_logs (log_type,severity,user_id,action,description,ip_address) VALUES ('admin','info',?,?,?,?)",
            array($_SESSION['user_id']??null, 'MAINTENANCE', $action, Security::getIp())
        );
    }
}
