<?php
class ForecastController {
    private $db;
    public function __construct() {
        Security::requireStrictAdmin();
        $this->db = Database::getInstance();
    }
    public function index(): void {
        // Last 30 days actual data
        $actual = $this->db->fetchAll(
            "SELECT DATE(processed_at) as d,
                    COALESCE(SUM(CASE WHEN status='success' THEN toll_amount ELSE 0 END),0) as revenue,
                    COUNT(CASE WHEN status='success' THEN 1 END) as tx_count
             FROM transactions
             WHERE processed_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
             GROUP BY DATE(processed_at) ORDER BY d"
        );
        // Build 7-day forecast using simple moving average of last 14 days
        $last14 = $this->db->fetchAll(
            "SELECT DAYOFWEEK(processed_at) as dow,
                    AVG(CASE WHEN status='success' THEN toll_amount ELSE 0 END) as avg_rev,
                    AVG(CASE WHEN status='success' THEN 1 ELSE 0 END)*COUNT(*) as avg_tx
             FROM transactions
             WHERE processed_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
             GROUP BY DAYOFWEEK(processed_at)"
        );
        $dowAvg = array();
        foreach ($last14 as $row) $dowAvg[(int)$row['dow']] = (float)$row['avg_rev'];

        $forecast = array();
        for ($i = 1; $i <= 7; $i++) {
            $date  = date('Y-m-d', strtotime("+{$i} days"));
            $dow   = (int)date('w', strtotime($date)) + 1;
            $base  = $dowAvg[$dow] ?? 0;
            // Add 5% growth trend per week
            $proj  = $base * (1 + 0.005 * $i);
            $forecast[] = array('date' => $date, 'projected_revenue' => round($proj, 2), 'day' => date('D M d', strtotime($date)));
        }

        $summary = array(
            'actual_30d'    => array_sum(array_column($actual, 'revenue')),
            'actual_7d'     => array_sum(array_column(array_slice($actual, -7), 'revenue')),
            'forecast_7d'   => array_sum(array_column($forecast, 'projected_revenue')),
            'best_day'      => !empty($actual) ? $actual[array_search(max(array_column($actual,'revenue')), array_column($actual,'revenue'))]['d'] : 'N/A',
            'best_revenue'  => !empty($actual) ? max(array_column($actual,'revenue')) : 0,
            'avg_daily'     => !empty($actual) ? array_sum(array_column($actual,'revenue'))/count($actual) : 0,
        );
        Response::view('admin/forecast', compact('actual','forecast','summary'), 'admin');
    }
}
