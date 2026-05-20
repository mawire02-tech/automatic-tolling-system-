<?php
class ShiftController {
    private $db;
    public function __construct() {
        Security::requireAdmin();
        $this->db = Database::getInstance();
    }
    public function index(): void {
        $activeShifts = $this->db->fetchAll(
            "SELECT s.*, u.full_name, u.username, d.device_name
             FROM operator_shifts s
             JOIN users u ON s.operator_id=u.id
             LEFT JOIN devices d ON s.device_id=d.id
             WHERE s.end_time IS NULL ORDER BY s.start_time DESC"
        );
        $shiftHistory = $this->db->fetchAll(
            "SELECT s.*, u.full_name, d.device_name,
                    TIMESTAMPDIFF(MINUTE, s.start_time, IFNULL(s.end_time,NOW())) as duration_min
             FROM operator_shifts s
             JOIN users u ON s.operator_id=u.id
             LEFT JOIN devices d ON s.device_id=d.id
             ORDER BY s.start_time DESC LIMIT 50"
        );
        $devices = $this->db->fetchAll("SELECT id, device_name, device_code FROM devices ORDER BY device_name");
        $operators = $this->db->fetchAll("SELECT id, full_name, username FROM users WHERE role='operator' AND status='active' ORDER BY full_name");
        $myShift = null;
        if (Security::isOperator()) {
            $myShift = $this->db->fetchOne(
                "SELECT s.*, d.device_name FROM operator_shifts s LEFT JOIN devices d ON s.device_id=d.id WHERE s.operator_id=? AND s.end_time IS NULL LIMIT 1",
                array($_SESSION['user_id'])
            );
        }
        Response::view('admin/shifts', compact('activeShifts','shiftHistory','devices','operators','myShift'), 'admin');
    }
    public function checkin(): void {
        if (!Security::verifyCsrf($_POST[CSRF_TOKEN_NAME]??'')) Response::error('Invalid CSRF',403);
        $opId    = Security::isAdmin() ? (int)($_POST['operator_id']??$_SESSION['user_id']) : (int)$_SESSION['user_id'];
        $devId   = (int)($_POST['device_id']??0);
        $notes   = Security::sanitize($_POST['notes']??'');
        // Check already active
        $active = $this->db->fetchOne("SELECT id FROM operator_shifts WHERE operator_id=? AND end_time IS NULL", array($opId));
        if ($active) Response::json(array('error'=>'Operator already has an active shift. Check out first.'),400);
        $this->db->execute(
            "INSERT INTO operator_shifts (operator_id, device_id, start_time, notes) VALUES (?,?,NOW(),?)",
            array($opId, $devId ?: null, $notes)
        );
        Response::json(array('success'=>true,'message'=>'Shift started successfully'));
    }
    public function checkout(): void {
        if (!Security::verifyCsrf($_POST[CSRF_TOKEN_NAME]??'')) Response::error('Invalid CSRF',403);
        $id = (int)($_POST['shift_id']??0);
        $this->db->execute(
            "UPDATE operator_shifts SET end_time=NOW() WHERE id=?" . (Security::isOperator() ? " AND operator_id=".(int)$_SESSION['user_id'] : ""),
            array($id)
        );
        Response::json(array('success'=>true,'message'=>'Shift ended'));
    }
}
