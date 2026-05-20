<?php
// app/controllers/AdminController.php

class AdminController {

    private $db;

    public function __construct() {
        Security::requireAdmin();
        $this->db = Database::getInstance();
    }

    private function userModel()     { return new UserModel(); }
    private function vehicleModel()  { return new VehicleModel(); }
    private function txModel()       { return new TransactionModel(); }
    private function deviceModel()   { return new DeviceModel(); }
    private function logModel()      { return new LogModel(); }
    private function topupModel()    { return new TopupModel(); }
    private function settingModel()  { return new SettingModel(); }

    // ── Dashboard (admin + operator) ──────────────────────────
    public function dashboard(): void {
        $dm = $this->deviceModel();
        $dm->markOfflineStale(2);
        $tm = $this->txModel();
        $isOp = Security::isOperator();

        // Operator: only see their assigned gate
        $assignedDeviceId = null;
        $assignedDevice   = null;
        if ($isOp) {
            $opUser = $this->db->fetchOne(
                "SELECT u.assigned_device_id, d.device_name, d.device_code, d.status as dstatus, d.barrier_status
                 FROM users u LEFT JOIN devices d ON u.assigned_device_id = d.id
                 WHERE u.id = ?", array($_SESSION['user_id'])
            );
            if ($opUser && $opUser['assigned_device_id']) {
                $assignedDeviceId = (int)$opUser['assigned_device_id'];
                $assignedDevice   = $opUser;
            }
        }

        // Build stats - operators only see their gate's tx count, no revenue
        $deviceFilter = $assignedDeviceId ? " AND device_id = {$assignedDeviceId}" : "";
        $stats = array(
            'total_users'        => $isOp ? null : $this->userModel()->count(array('role' => 'user')),
            'total_vehicles'     => $isOp ? null : $this->vehicleModel()->count(),
            'active_devices'     => $dm->count(array('status' => 'online')),
            'today_transactions' => (int)$this->db->fetchOne("SELECT COUNT(*) as c FROM transactions WHERE DATE(processed_at)=CURDATE() AND status='success'{$deviceFilter}")['c'],
            'pending_topups'     => $isOp ? null : $this->topupModel()->count(array('status' => 'pending')),
            'today_revenue'      => $isOp ? null : (float)$this->db->fetchOne("SELECT COALESCE(SUM(toll_amount),0) as r FROM transactions WHERE DATE(processed_at)=CURDATE() AND status='success'")['r'],
            'total_revenue'      => $isOp ? null : (float)$this->db->fetchOne("SELECT COALESCE(SUM(toll_amount),0) as r FROM transactions WHERE status='success'")['r'],
            'denied_today'       => (int)$this->db->fetchOne("SELECT COUNT(*) as c FROM transactions WHERE DATE(processed_at)=CURDATE() AND status='denied'{$deviceFilter}")['c'],
        );

        $pendingOperators = Security::isAdmin()
            ? (int)$this->db->fetchOne("SELECT COUNT(*) as c FROM users WHERE role='operator' AND status='pending'")['c']
            : 0;

        $revenueChart  = $isOp ? array() : $tm->getDailySummary(date('Y-m-d', strtotime('-6 days')), date('Y-m-d'));
        $vehicleStats  = $this->db->fetchAll("SELECT vehicle_type, COUNT(*) as count FROM transactions WHERE status='success' AND DATE(processed_at)=CURDATE(){$deviceFilter} GROUP BY vehicle_type");
        $txFilter      = $assignedDeviceId ? " AND t.device_id={$assignedDeviceId}" : "";
        $recentTx      = $this->db->fetchAll("SELECT t.*, v.plate_number, u.full_name, d.device_name FROM transactions t LEFT JOIN vehicles v ON t.vehicle_id=v.id LEFT JOIN users u ON t.user_id=u.id LEFT JOIN devices d ON t.device_id=d.id WHERE 1=1{$txFilter} ORDER BY t.processed_at DESC LIMIT 10");
        $allDevices    = $isOp
            ? ($assignedDeviceId ? $dm->findAll(array('id'=>$assignedDeviceId)) : array())
            : $dm->findAll(array(), 'status ASC, device_name ASC');
        $onlineDevices = $isOp
            ? ($assignedDevice ? array($assignedDevice) : array())
            : $dm->getOnline();
        $hourlyTraffic = $tm->getHourlyStats(date('Y-m-d'));

        // Route operators to their dedicated view
        if ($isOp) {
            Response::view('admin/operator_dashboard', compact(
                'stats','recentTx','hourlyTraffic','assignedDevice','isOp'
            ), 'admin');
        } else {
            Response::view('admin/dashboard', compact(
                'stats','revenueChart','vehicleStats','recentTx',
                'allDevices','onlineDevices','hourlyTraffic',
                'pendingOperators','assignedDevice','isOp'
            ), 'admin');
        }
    }

    // ── Users — admin only ────────────────────────────────────
    public function users(): void {
        Security::requireStrictAdmin();
        $search  = Security::sanitize($_GET['search'] ?? '');
        $role    = Security::sanitize($_GET['role']   ?? '');
        $status  = Security::sanitize($_GET['status'] ?? '');
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $result  = $this->userModel()->getPaginated($search, $role, $status, $page, 20);
        $devices = $this->deviceModel()->findAll(array(), 'device_name ASC');
        Response::view('admin/users', array('users'=>$result['data'],'total'=>$result['total'],'page'=>$page,'limit'=>20,'search'=>$search,'role'=>$role,'status'=>$status,'devices'=>$devices), 'admin');
    }

    public function saveUser(): void {
        Security::requireStrictAdmin();
        if (!Security::verifyCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) Response::error('Invalid CSRF', 403);
        $data = Security::sanitize($_POST);
        $id   = (int)($data['id'] ?? 0);
        $um   = $this->userModel();
        $v    = new Validator();
        $rules = array('full_name'=>'required|max:100|alpha_num|min:3|alpha','email'=>'required|email','role'=>'required|in:admin,operator,user','phone'=> 'required|phone','password'=> 'required|password');
        if (!$id) $rules['username'] = 'required|min:3|max:50|alpha_num|alpha';
        $v->validate($data, $rules);
        if ($v->fails()) Response::json(array('error'=>$v->firstError()), 400);
        if ($id) {
            $um->update($id, array('full_name'=>$data['full_name'],'email'=>$data['email'],'role'=>$data['role'],'status'=>$data['status']??'active','phone'=>$data['phone']??''));
            if (!empty($_POST['password'])) $um->update($id, array('password_hash'=>Security::hashPassword($_POST['password'])));
        } else {
            $um->create(array('username'=>$data['username'],'email'=>$data['email'],'password_hash'=>Security::hashPassword($_POST['password']??'Temp@1234'),'full_name'=>$data['full_name'],'phone'=>$data['phone']??'','role'=>$data['role'],'status'=>$data['status']??'active'));
        }
        $this->log('admin','info',$id?'USER_UPDATED':'USER_CREATED',"User: {$data['full_name']}");
        Response::json(array('success'=>true,'message'=>'User saved successfully'));
    }

    public function approveOperator(): void {
        Security::requireStrictAdmin();
        if (!Security::verifyCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) Response::error('Invalid CSRF', 403);
        $id     = (int)($_POST['id'] ?? 0);
        $action = Security::sanitize($_POST['action'] ?? '');
        if (!in_array($action, array('approve','reject'))) Response::error('Invalid action', 400);
        $newStatus = ($action === 'approve') ? 'active' : 'suspended';
        $this->db->execute("UPDATE users SET status = ? WHERE id = ? AND role = 'operator'", array($newStatus, $id));
        $this->log('admin','info',"OPERATOR_{$action}","Operator #{$id} {$action}d");
        Response::json(array('success'=>true,'message'=>'Operator '.($action==='approve'?'approved':'rejected')));
    }

    // ── Vehicles — admin only ─────────────────────────────────
    public function vehicles(): void {
        Security::requireStrictAdmin();
        $vm     = $this->vehicleModel();
        $search = Security::sanitize($_GET['search'] ?? '');
        $type   = Security::sanitize($_GET['type']   ?? '');
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $result = $vm->getPaginated($search, $type, $page, 20);
        Response::view('admin/vehicles', array('vehicles'=>$result['data'],'total'=>$result['total'],'page'=>$page,'limit'=>20,'search'=>$search,'type'=>$type,'userList'=>$vm->getActiveUsers()), 'admin');
    }

    public function saveVehicle(): void {
        Security::requireStrictAdmin();
        if (!Security::verifyCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) Response::error('Invalid CSRF', 403);
        $data   = Security::sanitize($_POST);
        $id     = (int)($data['id'] ?? 0);
        $userId = (int)($data['user_id'] ?? 0);
        $vm     = $this->vehicleModel();
        if (!$id && !$userId) Response::json(array('error'=>'Please select an owner'), 400);
        $v = new Validator();
        $v->validate($data, array('plate_number'=>'required|max:20','vehicle_type'=>'required|in:motorcycle,car,suv,truck,bus','rfid_tag'=> 'required|rfid','make'=> 'required|alpha','model'=> 'required|alpha','color'=> 'required|alpha','year'=> 'required|numeric|min_val:1900|year'));
        if ($v->fails()) Response::json(array('error'=>$v->firstError()), 400);
        $rfidTag = !empty($data['rfid_tag']) ? strtoupper(trim($data['rfid_tag'])) : null;
        $vdata   = array('plate_number'=>strtoupper($data['plate_number']),'vehicle_type'=>$data['vehicle_type'],'make'=>$data['make']??'','model'=>$data['model']??'','year'=>!empty($data['year'])?$data['year']:null,'color'=>$data['color']??'','status'=>$data['status']??'active','rfid_tag'=>$rfidTag);
        $rfidModel = new RfidModel();
        if ($id) {
            $existing = $vm->find($id);
            $ownerId  = $existing ? (int)$existing['user_id'] : $userId;
            $vm->update($id, $vdata);
            if ($rfidTag) $rfidModel->assignToVehicle($rfidTag, $id, $ownerId);
        } else {
            $vdata['user_id'] = $userId;
            $vid = $vm->create($vdata);
            if ($rfidTag) $rfidModel->assignToVehicle($rfidTag, $vid, $userId);
        }
        $this->log('admin','info',$id?'VEHICLE_UPDATED':'VEHICLE_REGISTERED',"Plate: ".strtoupper($data['plate_number']));
        Response::json(array('success'=>true,'message'=>'Vehicle saved successfully'));
    }
        // ── Delete Vehicle (Hard Delete) — admin only ─────────────
    public function deleteVehicle(): void {
        Security::requireStrictAdmin();

        if (!Security::verifyCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
            Response::json(array('error' => 'Invalid CSRF token'), 403);
            return;
        }

        $vehicleId = (int)($_POST['vehicle_id'] ?? 0);
        if (!$vehicleId) {
            Response::json(array('error' => 'Invalid vehicle ID'), 400);
            return;
        }

        // Check vehicle exists before doing anything
        $vehicle = $this->db->fetchOne(
            "SELECT v.id, v.plate_number, v.rfid_tag, v.user_id, u.full_name
             FROM vehicles v
             LEFT JOIN users u ON v.user_id = u.id
             WHERE v.id = ?",
            array($vehicleId)
        );

        if (!$vehicle) {
            Response::json(array('error' => 'Vehicle not found'), 404);
            return;
        }

        try {
            $this->db->execute("START TRANSACTION");

            // 1. Nullify vehicle_id on rfid_cards (FK is SET NULL but we hard-delete the card row)
            $this->db->execute(
                "DELETE FROM rfid_cards WHERE vehicle_id = ?",
                array($vehicleId)
            );

            // 2. Nullify vehicle_id on transactions (FK is SET NULL — we delete the rows entirely)
            $this->db->execute(
                "DELETE FROM transactions WHERE vehicle_id = ?",
                array($vehicleId)
            );

            // 3. Delete any system logs referencing this vehicle's plate (description match)
            //    — logs have no vehicle_id FK so we clean by plate reference
            $this->db->execute(
                "DELETE FROM system_logs WHERE description LIKE ?",
                array('%' . $vehicle['plate_number'] . '%')
            );

            // 4. Delete the vehicle itself (CASCADE will handle any remaining FKs)
            $this->db->execute(
                "DELETE FROM vehicles WHERE id = ?",
                array($vehicleId)
            );

            $this->db->execute("COMMIT");

            // Audit log for the deletion
            $this->log(
                'admin',
                'warning',
                'VEHICLE_DELETED',
                "Vehicle #{$vehicleId} plate {$vehicle['plate_number']} permanently deleted by admin. Owner: {$vehicle['full_name']}"
            );

            Response::json(array(
                'success' => true,
                'message' => "Vehicle {$vehicle['plate_number']} and all related records permanently deleted."
            ));

        } catch (Exception $e) {
            $this->db->execute("ROLLBACK");
            $this->log('admin', 'error', 'VEHICLE_DELETE_FAILED', "Failed to delete vehicle #{$vehicleId}: " . $e->getMessage());
            Response::json(array('error' => 'Delete failed. Please try again.'), 500);
        }
    }

    // ── Transactions — admin only ─────────────────────────────
    public function transactions(): void {
        Security::requireStrictAdmin();
        $search=$_GET['search']??''; $dateFrom=$_GET['date_from']??date('Y-m-d',strtotime('-30 days')); $dateTo=$_GET['date_to']??date('Y-m-d'); $status=$_GET['status']??''; $deviceId=(int)($_GET['device_id']??0); $page=max(1,(int)($_GET['page']??1));
        $result=$this->txModel()->getPaginated(Security::sanitize($search),Security::sanitize($dateFrom),Security::sanitize($dateTo),Security::sanitize($status),$deviceId,0,$page,20);
        Response::view('admin/transactions', array('txs'=>$result['data'],'total'=>$result['total'],'revenue'=>$result['revenue'],'page'=>$page,'limit'=>20,'devices'=>$this->deviceModel()->findAll(array(),'device_name ASC'),'search'=>Security::sanitize($search),'dateFrom'=>Security::sanitize($dateFrom),'dateTo'=>Security::sanitize($dateTo),'status'=>Security::sanitize($status),'deviceId'=>$deviceId), 'admin');
    }

    // ── Top-Ups — admin only ──────────────────────────────────
    public function topups(): void {
        Security::requireStrictAdmin();
        $status = Security::sanitize($_GET['status'] ?? 'pending');
        Response::view('admin/topups', array('requests'=>$this->topupModel()->getWithUser($status),'status'=>$status), 'admin');
    }

    public function approveTopup(): void {
        Security::requireStrictAdmin();
        if (!Security::verifyCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) Response::error('Invalid CSRF', 403);
        $id=$_POST['id']??0; $action=Security::sanitize($_POST['action']??''); $note=Security::sanitize($_POST['note']??'');
        if (!in_array($action,array('approved','rejected'))) Response::error('Invalid action',400);
        $tm=$this->topupModel();
        try { if($action==='approved') $tm->approve((int)$id,(int)$_SESSION['user_id'],$note); else $tm->reject((int)$id,(int)$_SESSION['user_id'],$note); $this->log('admin','info',"TOPUP_{$action}","Top-up #{$id} {$action}"); Response::json(array('success'=>true,'message'=>"Top-up request {$action}")); }
        catch (Exception $e) { Response::error('Processing failed',500); }
    }

    // ── Devices — admin only ──────────────────────────────────
    public function devices(): void {
        Security::requireStrictAdmin();
        Response::view('admin/devices', array('devices'=>$this->deviceModel()->findAll(array(),'status ASC, device_name ASC')), 'admin');
    }

    public function saveDevice(): void {
        Security::requireStrictAdmin();
        if (!Security::verifyCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) Response::error('Invalid CSRF', 403);
        $data=$_POST; $id=(int)($data['id']??0); $dm=$this->deviceModel();
        $v=new Validator(); $rules=array('device_name'=>'required|max:100|alpha_num|alpha','location'=>'required|max:200|alpha'); if(!$id) $rules['device_code']='required|max:20|min:5'; $v->validate(Security::sanitize($data),$rules); if($v->fails()) Response::json(array('error'=>$v->firstError()),400);
        if ($id) { $dm->update($id,array('device_name'=>Security::sanitize($data['device_name']),'location'=>Security::sanitize($data['location']),'status'=>$data['status']??'offline')); Response::json(array('success'=>true,'message'=>'Device updated successfully')); }
        else { $k=Security::generateApiKey(); $dm->create(array('device_code'=>strtoupper(Security::sanitize($data['device_code'])),'device_name'=>Security::sanitize($data['device_name']),'location'=>Security::sanitize($data['location']),'api_key'=>$k,'status'=>'offline')); Response::json(array('success'=>true,'message'=>'Device created. Copy your API Key now!','api_key'=>$k)); }
    }

    // ── Logs — admin only ─────────────────────────────────────
    public function logs(): void {
        Security::requireStrictAdmin();
        $type=Security::sanitize($_GET['type']??''); $severity=Security::sanitize($_GET['severity']??''); $dateFrom=Security::sanitize($_GET['date_from']??date('Y-m-d',strtotime('-7 days'))); $dateTo=Security::sanitize($_GET['date_to']??date('Y-m-d')); $page=max(1,(int)($_GET['page']??1));
        $result=$this->logModel()->getPaginated($type,$severity,$dateFrom,$dateTo,$page,50);
        Response::view('admin/logs',array('logs'=>$result['data'],'total'=>$result['total'],'page'=>$page,'limit'=>50,'type'=>$type,'severity'=>$severity,'dateFrom'=>$dateFrom,'dateTo'=>$dateTo),'admin');
    }

    // ── Settings — admin only ─────────────────────────────────
    public function settings(): void {
        Security::requireStrictAdmin();
        Response::view('admin/settings', array('settings'=>$this->settingModel()->getAllGrouped()), 'admin');
    }

    public function saveSettings(): void {
        Security::requireStrictAdmin();
        if (!Security::verifyCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) Response::error('Invalid CSRF', 403);
        $sm=$this->settingModel(); $settingsData=$_POST['settings']??array();
        foreach ($settingsData as $k=>$v) $sm->set(Security::sanitize($k),Security::sanitize($v),(int)$_SESSION['user_id']);
        Security::clearCurrencyCache();
        $this->log('admin','info','SETTINGS_UPDATED','System settings updated');
        Response::json(array('success'=>true,'message'=>'Settings saved successfully'));
    }

    // ── API stats (admin + operator) ──────────────────────────
    public function apiStats(): void {
        $dm=$this->deviceModel(); $dm->markOfflineStale(2);
        Response::json(array('online_devices'=>$dm->count(array('status'=>'online')),'offline_devices'=>$dm->count(array('status'=>'offline')),'today_revenue'=>(float)$this->db->fetchOne("SELECT COALESCE(SUM(toll_amount),0) as r FROM transactions WHERE DATE(processed_at)=CURDATE() AND status='success'")['r'],'today_count'=>(int)$this->db->fetchOne("SELECT COUNT(*) as c FROM transactions WHERE DATE(processed_at)=CURDATE() AND status='success'")['c'],'pending_topups'=>$this->topupModel()->count(array('status'=>'pending')),'active_users'=>$this->userModel()->count(array('status'=>'active','role'=>'user'))));
    }


    // ── Assign Gate to Operator — admin only ─────────────────
    public function assignGate(): void {
        Security::requireStrictAdmin();

        // JS posts as 'csrf_token', so read that key directly
        $token = $_POST['csrf_token'] ?? '';
        if (!Security::verifyCsrf($token)) {
            Response::json(array('success' => false, 'message' => 'Invalid CSRF token'), 403);
            return;
        }

        $operatorId = (int)($_POST['operator_id'] ?? 0);
        $deviceId   = (int)($_POST['device_id']   ?? 0);

        if (!$operatorId) {
            Response::json(array('success' => false, 'message' => 'Invalid operator'), 400);
            return;
        }

        $op = $this->db->fetchOne(
            "SELECT id, full_name FROM users WHERE id = ? AND role = 'operator'",
            array($operatorId)
        );
        if (!$op) {
            Response::json(array('success' => false, 'message' => 'Operator not found'), 404);
            return;
        }

        if ($deviceId) {
            $device = $this->db->fetchOne(
                "SELECT id, device_name, status FROM devices WHERE id = ?",
                array($deviceId)
            );
            if (!$device) {
                Response::json(array('success' => false, 'message' => 'Device not found'), 404);
                return;
            }
            $this->db->execute(
                "UPDATE users SET assigned_device_id = ? WHERE id = ? AND role = 'operator'",
                array($deviceId, $operatorId)
            );
            $this->log('admin', 'info', 'GATE_ASSIGNED',
                "Gate '{$device['device_name']}' assigned to operator '{$op['full_name']}'");
            Response::json(array(
                'success' => true,
                'message' => 'Gate assigned successfully',
                'gate'    => array(
                    'device_id'   => $device['id'],
                    'device_name' => $device['device_name'],
                    'gate_status' => $device['status'],
                )
            ));
        } else {
            $this->db->execute(
                "UPDATE users SET assigned_device_id = NULL WHERE id = ? AND role = 'operator'",
                array($operatorId)
            );
            $this->log('admin', 'info', 'GATE_REMOVED',
                "Gate assignment removed from operator '{$op['full_name']}'");
            Response::json(array('success' => true, 'message' => 'Gate assignment removed', 'gate' => null));
        }
    }

    private function log($type, $severity, $action, $desc) {
        $this->logModel()->write($type, $severity, $_SESSION['user_id'] ?? null, null, $action, $desc, Security::getIp());
    }
}
