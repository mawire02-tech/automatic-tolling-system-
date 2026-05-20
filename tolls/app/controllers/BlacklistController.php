<?php
class BlacklistController {
    private $db;
    public function __construct() {
        Security::requireStrictAdmin();
        $this->db = Database::getInstance();
    }
    public function index(): void {
        $list = $this->db->fetchAll(
            "SELECT b.*, u.full_name as added_by_name
             FROM vehicle_blacklist b
             LEFT JOIN users u ON b.added_by = u.id
             ORDER BY b.created_at DESC"
        );
        $vehicles = $this->db->fetchAll("SELECT v.id, v.plate_number, v.rfid_tag, u.full_name FROM vehicles v LEFT JOIN users u ON v.user_id=u.id ORDER BY v.plate_number");
        Response::view('admin/blacklist', compact('list','vehicles'), 'admin');
    }
    public function save(): void {

    if (!Security::verifyCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
        Response::error('Invalid CSRF', 403);
    }

    $plate  = strtoupper(trim(Security::sanitize($_POST['plate_number'] ?? '')));
    $reason = Security::sanitize($_POST['reason'] ?? '');

    if (!$plate) {
        Response::json(array('error' => 'Plate number required'), 400);
    }

    // CHECK IF VEHICLE EXISTS
    $vehicle = $this->db->fetchOne(
        "SELECT id FROM vehicles WHERE plate_number = ? LIMIT 1",
        array($plate)
    );

    if (!$vehicle) {
        Response::json(array('error' => 'Vehicle does not exist'), 400);
    }

    // CHECK EXISTING BLACKLIST
    $ex = $this->db->fetchOne(
        "SELECT id FROM vehicle_blacklist WHERE plate_number = ?",
        array($plate)
    );

    if ($ex) {
        Response::json(array('error' => 'Vehicle already on blacklist'), 400);
    }

    $this->db->execute(
        "INSERT INTO vehicle_blacklist (plate_number, reason, added_by, created_at)
         VALUES (?,?,?,NOW())",
        array($plate, $reason, $_SESSION['user_id'])
    );

    // SUSPEND VEHICLE
    $this->db->execute(
        "UPDATE vehicles SET status='suspended' WHERE plate_number=?",
        array($plate)
    );

    Response::json(array(
        'success' => true,
        'message' => "Vehicle {$plate} added to blacklist"
    ));
}
    public function remove(): void {
        if (!Security::verifyCsrf($_POST[CSRF_TOKEN_NAME]??'')) Response::error('Invalid CSRF',403);
        $id = (int)($_POST['id']??0);
        $row = $this->db->fetchOne("SELECT plate_number FROM vehicle_blacklist WHERE id=?", array($id));
        if (!$row) Response::json(array('error'=>'Record not found'),404);
        $this->db->execute("DELETE FROM vehicle_blacklist WHERE id=?", array($id));
        $this->db->execute("UPDATE vehicles SET status='active' WHERE plate_number=?", array($row['plate_number']));
        Response::json(array('success'=>true,'message'=>'Vehicle removed from blacklist'));
    }
}
