<?php
// app/controllers/TollController.php

class TollController {

    private Database         $db;
    private DeviceModel      $deviceModel;
    private RfidModel        $rfidModel;
    private TransactionModel $txModel;
    private SettingModel     $settingModel;
    private LogModel         $logModel;

    public function __construct() {
        $this->db           = Database::getInstance();
        $this->deviceModel  = new DeviceModel();
        $this->rfidModel    = new RfidModel();
        $this->txModel      = new TransactionModel();
        $this->settingModel = new SettingModel();
        $this->logModel     = new LogModel();
    }

    private function authDevice(): array {
        $key    = isset($_SERVER['HTTP_X_API_KEY']) ? $_SERVER['HTTP_X_API_KEY'] : (isset($_GET['api_key']) ? $_GET['api_key'] : '');
        $device = Security::validateApiKey($key);
        if (!$device) Response::error('Unauthorized: Invalid API key', 401);
        return $device;
    }

    // ── POST /api/v1/toll/process ─────────────────────────────
    public function processRFID(): void {
        $device  = $this->authDevice();
        $payload = json_decode(file_get_contents('php://input'), true);
        if (!$payload) Response::error('Invalid JSON payload', 400);

        $rfidUid  = strtoupper(trim(Security::sanitize($payload['rfid_uid'] ?? '')));
        $vtype    = Security::sanitize($payload['vehicle_type'] ?? 'car');
        $direction= Security::sanitize($payload['direction']    ?? 'both');

        if (empty($rfidUid)) Response::error('Missing RFID UID', 400);

        // Update heartbeat
        $this->deviceModel->heartbeat($device['id'], Security::getIp(), $payload['barrier_status'] ?? 'unknown', $payload['firmware'] ?? '');

        // Look up by rfid_cards first
        $card = $this->rfidModel->findByUid($rfidUid);

        // Fallback: look up by vehicles.rfid_tag directly
        // ── Blacklist check ──────────────────────────────────
        // Check plate number against blacklist BEFORE processing
        if ($card && !empty($card['plate_number'])) {
            $blacklisted = $this->db->fetchOne(
                "SELECT reason FROM vehicle_blacklist WHERE plate_number = ? LIMIT 1",
                array($card['plate_number'])
            );
            if ($blacklisted) {
                $this->txModel->record(array('device_id'=>$device['id'],'vehicle_id'=>$card['vehicle_id'],'user_id'=>$card['uid'],'rfid_uid'=>$rfidUid,'toll_amount'=>0,'vehicle_type'=>$card['vtype']??'car','status'=>'denied','deny_reason'=>'BLACKLISTED'));
                $this->logModel->write('security','warning',$card['uid'],$device['id'],'BLACKLIST_HIT',"Blacklisted vehicle: {$card['plate_number']}",Security::getIp());
                Response::json(array('allow'=>false,'reason'=>'BLACKLISTED','message'=>'Vehicle is blacklisted','buzzer'=>'deny','led'=>'red','display'=>'BLACKLISTED       '));
            }
        }

        if (!$card) {
            $vehicle = $this->db->fetchOne(
                "SELECT v.*, u.id AS uid, u.wallet_balance, u.full_name, u.status AS ustatus
                 FROM vehicles v
                 JOIN users u ON v.user_id = u.id
                 WHERE UPPER(v.rfid_tag) = ?
                 LIMIT 1",
                array($rfidUid)
            );
            if ($vehicle) {
                // Auto-create rfid_cards entry
                $this->rfidModel->assignToVehicle($rfidUid, (int)$vehicle['id'], (int)$vehicle['uid']);
                // Re-fetch properly joined
                $card = $this->rfidModel->findByUid($rfidUid);
            }
        }

        // ── Blacklist check ──────────────────────────────────
        // Check plate number against blacklist BEFORE processing
        if ($card && !empty($card['plate_number'])) {
            $blacklisted = $this->db->fetchOne(
                "SELECT reason FROM vehicle_blacklist WHERE plate_number = ? LIMIT 1",
                array($card['plate_number'])
            );
            if ($blacklisted) {
                $this->txModel->record(array('device_id'=>$device['id'],'vehicle_id'=>$card['vehicle_id'],'user_id'=>$card['uid'],'rfid_uid'=>$rfidUid,'toll_amount'=>0,'vehicle_type'=>$card['vtype']??'car','status'=>'denied','deny_reason'=>'BLACKLISTED'));
                $this->logModel->write('security','warning',$card['uid'],$device['id'],'BLACKLIST_HIT',"Blacklisted vehicle: {$card['plate_number']}",Security::getIp());
                Response::json(array('allow'=>false,'reason'=>'BLACKLISTED','message'=>'Vehicle is blacklisted','buzzer'=>'deny','led'=>'red','display'=>'BLACKLISTED       '));
            }
        }

        if (!$card) {
            $this->txModel->record(array('device_id'=>$device['id'],'rfid_uid'=>$rfidUid,'toll_amount'=>0,'vehicle_type'=>$vtype,'status'=>'denied','deny_reason'=>'UNKNOWN_RFID'));
            $this->logModel->write('security','warning',null,$device['id'],'UNKNOWN_RFID',"Unknown RFID: {$rfidUid}",Security::getIp());
            Response::json(array('allow'=>false,'reason'=>'UNKNOWN_RFID','message'=>'Unregistered RFID tag','buzzer'=>'deny','led'=>'red','display'=>'ACCESS DENIED     '));
        }

        // Check vehicle blacklist
        $blacklisted = $this->db->fetchOne(
            "SELECT reason FROM vehicle_blacklist WHERE plate_number = ? LIMIT 1",
            array($card['plate_number'] ?? '')
        );
        if ($blacklisted) {
            $this->txModel->record(array('device_id'=>$device['id'],'vehicle_id'=>$card['vehicle_id'],'user_id'=>$card['uid'],'rfid_uid'=>$rfidUid,'toll_amount'=>0,'vehicle_type'=>$vtype,'status'=>'denied','deny_reason'=>'BLACKLISTED'));
            $this->logModel->write('security','error',$card['uid'],$device['id'],'BLACKLIST_DENIED',"Blacklisted vehicle {$card['plate_number']} attempted entry",Security::getIp());
            Response::json(array('allow'=>false,'reason'=>'BLACKLISTED','message'=>'Vehicle is blacklisted','buzzer'=>'deny','led'=>'red','display'=>'VEHICLE BANNED    '));
        }

        if ($card['vstatus'] !== 'active') {
            $this->txModel->record(array('device_id'=>$device['id'],'vehicle_id'=>$card['vehicle_id'],'user_id'=>$card['uid'],'rfid_uid'=>$rfidUid,'toll_amount'=>0,'vehicle_type'=>$vtype,'status'=>'denied','deny_reason'=>'VEHICLE_SUSPENDED'));
            Response::json(array('allow'=>false,'reason'=>'VEHICLE_SUSPENDED','message'=>'Vehicle suspended','buzzer'=>'deny','led'=>'red','display'=>'VEH SUSPENDED    '));
        }
        if ($card['ustatus'] !== 'active') {
            $this->txModel->record(array('device_id'=>$device['id'],'vehicle_id'=>$card['vehicle_id'],'user_id'=>$card['uid'],'rfid_uid'=>$rfidUid,'toll_amount'=>0,'vehicle_type'=>$vtype,'status'=>'denied','deny_reason'=>'ACCOUNT_SUSPENDED'));
            Response::json(array('allow'=>false,'reason'=>'ACCOUNT_SUSPENDED','message'=>'Account suspended','buzzer'=>'deny','led'=>'red','display'=>'ACCT SUSPENDED   '));
        }

        $feeVtype = !empty($card['vtype']) ? $card['vtype'] : 'car';
        $tollFee  = $this->settingModel->getTollFee($feeVtype);
        $balance  = (float)$card['wallet_balance'];

        if ($balance < $tollFee) {
            $this->txModel->record(array('device_id'=>$device['id'],'vehicle_id'=>$card['vehicle_id'],'user_id'=>$card['uid'],'rfid_uid'=>$rfidUid,'toll_amount'=>$tollFee,'balance_before'=>$balance,'balance_after'=>$balance,'vehicle_type'=>$feeVtype,'status'=>'denied','deny_reason'=>'INSUFFICIENT_BALANCE'));
            Response::json(array('allow'=>false,'reason'=>'INSUFFICIENT_BALANCE','message'=>"Need PHP{$tollFee} have PHP{$balance}",'buzzer'=>'deny','led'=>'red','balance'=>$balance,'display'=>'LOW BALANCE      '));
        }

        // Deduct toll
        $this->db->beginTransaction();
        try {
            $newBalance = $balance - $tollFee;
            $this->db->execute("UPDATE users SET wallet_balance = ? WHERE id = ?", array($newBalance, $card['uid']));
            $ref = Security::generateRef('TXN');
            $this->db->execute(
                "INSERT INTO transactions (transaction_ref,device_id,vehicle_id,user_id,rfid_uid,toll_amount,balance_before,balance_after,vehicle_type,direction,status,is_offline) VALUES (?,?,?,?,?,?,?,?,?,?,'success',0)",
                array($ref,$device['id'],$card['vehicle_id'],$card['uid'],$rfidUid,$tollFee,$balance,$newBalance,$feeVtype,$direction)
            );
            $this->deviceModel->incrementStats($device['id'], $tollFee);
            $this->rfidModel->touch($rfidUid);
            $this->db->commit();

            $firstName = isset(explode(' ',$card['full_name'])[0]) ? explode(' ',$card['full_name'])[0] : 'USER';
            $this->logModel->write('transaction','info',$card['uid'],$device['id'],'TOLL_DEDUCTED',"PHP{$tollFee} for {$card['plate_number']}",Security::getIp());

            Response::json(array(
                'allow'        => true,
                'message'      => 'Access granted',
                'ref'          => $ref,
                'plate'        => $card['plate_number'],
                'owner'        => $card['full_name'],
                'toll'         => $tollFee,
                'balance'      => $newBalance,
                'buzzer'       => 'allow',
                'led'          => 'green',
                'display'      => substr("OK {$firstName} PHP{$newBalance}",0,16),
                'barrier_cmd'  => 'open',
                'vehicle_type' => $feeVtype,
            ));
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Transaction failed: " . $e->getMessage());
            Response::error('Transaction failed', 500);
        }
    }

    // ── POST /api/v1/device/heartbeat ─────────────────────────
    public function heartbeat(): void {
        $device  = $this->authDevice();
        $payload = json_decode(file_get_contents('php://input'), true);
        if (!$payload) $payload = array();

        // Update IP from payload if provided (more reliable than REMOTE_ADDR via NAT)
        $ip = !empty($payload['ip']) ? $payload['ip'] : Security::getIp();
        $this->deviceModel->heartbeat($device['id'], $ip, $payload['barrier_status'] ?? 'unknown', $payload['firmware'] ?? '');

        // Ack any commands the ESP32 just executed
        if (!empty($payload['acks']) && is_array($payload['acks'])) {
            foreach ($payload['acks'] as $ack) {
                if (!empty($ack['id'])) {
                    $this->deviceModel->ackCommand((int)$ack['id'], $ack['result'] ?? 'ok');
                }
            }
        }

        // Pull config
        $settings = $this->db->fetchAll("SELECT setting_key, setting_value FROM system_settings WHERE setting_group IN ('barrier','toll_fees','device')");
        $config = array();
        foreach ($settings as $s) $config[$s['setting_key']] = $s['setting_value'];

        // Return pending commands for ESP32 to execute
        $commands = $this->deviceModel->getPendingCommands($device['id']);

        Response::json(array(
            'status'   => 'ok',
            'ts'       => time(),
            'config'   => $config,
            'commands' => $commands,
        ));
    }

    // ── POST /api/v1/device/commands (ack only) ───────────────
    public function pollCommands(): void {
        $device  = $this->authDevice();
        $payload = json_decode(file_get_contents('php://input'), true);
        if (!$payload) $payload = array();

        if (!empty($payload['acks']) && is_array($payload['acks'])) {
            foreach ($payload['acks'] as $ack) {
                if (!empty($ack['id'])) {
                    $this->deviceModel->ackCommand((int)$ack['id'], $ack['result'] ?? 'ok');
                }
            }
        }
        if (!empty($payload['barrier_status'])) {
            $this->deviceModel->updateBarrierStatus($device['id'], $payload['barrier_status']);
        }
        Response::json(array('commands' => $this->deviceModel->getPendingCommands($device['id'])));
    }
}
