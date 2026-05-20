<?php
// app/controllers/ReportsController.php

class ReportsController {

    private TransactionModel $txModel;
    private DeviceModel      $deviceModel;
    private LogModel         $logModel;

    public function __construct() {
        Security::requireStrictAdmin();
        $this->txModel     = new TransactionModel();
        $this->deviceModel = new DeviceModel();
        $this->logModel    = new LogModel();
    }

    public function index(): void {
        $dateFrom = Security::sanitize($_GET['date_from'] ?? date('Y-m-d', strtotime('-29 days')));
        $dateTo   = Security::sanitize($_GET['date_to']   ?? date('Y-m-d'));

        // Clamp range to 90 days max
        $diff = (strtotime($dateTo) - strtotime($dateFrom)) / 86400;
        if ($diff > 90) $dateFrom = date('Y-m-d', strtotime($dateTo . ' -89 days'));

        // ── Daily summary (revenue trend + success rate) ──────
        $dailySummary = $this->txModel->getDailySummary($dateFrom, $dateTo);

        // ── Tag result breakdown (doughnut) ───────────────────
        // success + deny_reason breakdown
        $db = Database::getInstance();
        $tagBreakdown = $db->fetchAll(
            "SELECT
               CASE
                 WHEN status = 'success'                    THEN 'Success'
                 WHEN deny_reason = 'UNKNOWN_RFID'         THEN 'Unknown RFID'
                 WHEN deny_reason = 'INSUFFICIENT_BALANCE' THEN 'Insufficient Balance'
                 WHEN deny_reason = 'VEHICLE_SUSPENDED'    THEN 'Vehicle Suspended'
                 WHEN deny_reason = 'ACCOUNT_SUSPENDED'    THEN 'Account Suspended'
                 WHEN deny_reason IS NOT NULL               THEN deny_reason
                 ELSE 'Other'
               END as label,
               COUNT(*) as count
             FROM transactions
             WHERE DATE(processed_at) BETWEEN ? AND ?
             GROUP BY label
             ORDER BY count DESC",
            [$dateFrom, $dateTo]
        );

        // ── Gate (device) performance ─────────────────────────
        $gatePerformance = $this->txModel->getGatePerformance($dateFrom, $dateTo);

        // ── Vehicle class stats ───────────────────────────────
        $vehicleClass = $this->txModel->getVehicleClassStats($dateFrom, $dateTo);

        // ── Summary totals ────────────────────────────────────
        $totals = $db->fetchOne(
            "SELECT
               COALESCE(SUM(CASE WHEN status='success' THEN toll_amount ELSE 0 END),0) as total_revenue,
               COUNT(CASE WHEN status='success' THEN 1 END) as total_success,
               COUNT(CASE WHEN status='denied'  THEN 1 END) as total_denied,
               COUNT(*) as total_scans,
               COUNT(DISTINCT user_id) as unique_users,
               COUNT(DISTINCT vehicle_id) as unique_vehicles
             FROM transactions
             WHERE DATE(processed_at) BETWEEN ? AND ?",
            [$dateFrom, $dateTo]
        );

        // ── Avg per day ───────────────────────────────────────
        $days              = max(1, $diff + 1);
        $avgDailyRevenue   = round((float)$totals['total_revenue'] / $days, 2);
        $avgDailyTx        = round((int)$totals['total_success']   / $days, 1);
        $successRate       = $totals['total_scans'] > 0
            ? round(($totals['total_success'] / $totals['total_scans']) * 100, 1)
            : 0;

        $devices = $this->deviceModel->findAll([], 'device_name ASC');

        Response::view('admin/reports', compact(
            'dateFrom', 'dateTo',
            'dailySummary', 'tagBreakdown', 'gatePerformance', 'vehicleClass',
            'totals', 'avgDailyRevenue', 'avgDailyTx', 'successRate',
            'devices', 'days'
        ), 'admin');
    }
}
