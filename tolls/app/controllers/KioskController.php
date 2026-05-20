<?php
class KioskController {
    private $db;
    public function __construct() {
        Security::requireAdmin();
        $this->db = Database::getInstance();
    }
    public function index(): void {
        $recentTopups = $this->db->fetchAll(
            "SELECT t.*, u.full_name, u.wallet_balance FROM topup_requests t
             JOIN users u ON t.user_id=u.id
             WHERE t.payment_method='cash' AND t.status='approved'
             ORDER BY t.processed_at DESC LIMIT 20"
        );
        Response::view('admin/kiosk', compact('recentTopups'), 'admin');
    }
    public function processTopup(): void {
        if (!Security::verifyCsrf($_POST[CSRF_TOKEN_NAME]??'')) Response::error('Invalid CSRF',403);
        $plate  = strtoupper(trim(Security::sanitize($_POST['plate_number']??'')));
        $amount = (float)($_POST['amount']??0);
        $refNo  = strtoupper(trim(Security::sanitize($_POST['reference_number']??'')));
        if (!$plate)      Response::json(array('error'=>'Plate number required'),400);
        if ($amount < 1)  Response::json(array('error'=>'Amount must be at least '.Security::currency().'1'),400);
        if (!$refNo)      Response::json(array('error'=>'Receipt number required'),400);
        // Check unique reference
        $ex = $this->db->fetchOne("SELECT id FROM topup_requests WHERE reference_number=?", array($refNo));
        if ($ex) Response::json(array('error'=>'This receipt number has already been used'),400);
        // Find vehicle owner
        $vehicle = $this->db->fetchOne(
            "SELECT v.*, u.id as uid, u.full_name, u.wallet_balance, u.email FROM vehicles v JOIN users u ON v.user_id=u.id WHERE v.plate_number=? LIMIT 1",
            array($plate)
        );
        if (!$vehicle) Response::json(array('error'=>"Vehicle {$plate} not found in system"),404);
        if ($vehicle['status'] !== 'active') Response::json(array('error'=>'Vehicle is suspended'),400);
        $this->db->beginTransaction();
        try {
            $newBal = (float)$vehicle['wallet_balance'] + $amount;
            $ref    = Security::generateRef('KSK');
            $this->db->execute("UPDATE users SET wallet_balance=? WHERE id=?", array($newBal, $vehicle['uid']));
            $this->db->execute(
                "INSERT INTO topup_requests (user_id,request_ref,amount,payment_method,reference_number,status,admin_note,processed_at,requested_at) VALUES (?,?,'cash',?,?,'approved','Kiosk cash top-up by operator',NOW(),NOW())",
                array($vehicle['uid'], $ref, $amount, $refNo)
            );
            $this->db->commit();
            Response::json(array(
                'success'     => true,
                'message'     => "Top-up successful! {$vehicle['full_name']}'s balance is now ".Security::currency().number_format($newBal,2),
                'new_balance' => $newBal,
                'owner'       => $vehicle['full_name'],
                'ref'         => $ref,
            ));
        } catch (Exception $e) {
            $this->db->rollBack();
            Response::error('Top-up failed',500);
        }
    }
}
