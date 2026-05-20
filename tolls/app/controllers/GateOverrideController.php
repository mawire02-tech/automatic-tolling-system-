<?php
// app/controllers/GateOverrideController.php
// Commands execute via fast heartbeat queue (ESP32 polls every 3 seconds)

class GateOverrideController {

    private DeviceModel $deviceModel;
    private LogModel    $logModel;

    public function __construct() {
        Security::requireAdmin();
        $this->deviceModel = new DeviceModel();
        $this->logModel    = new LogModel();
    }

    public function index(): void {
        $this->deviceModel->markOfflineStale(2);

        // Operator: only see their assigned gate
        if (Security::isOperator()) {
            $db  = Database::getInstance();
            $row = $db->fetchOne("SELECT assigned_device_id FROM users WHERE id=?", array($_SESSION['user_id']));
            $aid = $row ? (int)$row['assigned_device_id'] : 0;
            $devices = $aid
                ? $this->deviceModel->findAll(array('id'=>$aid), 'device_name ASC')
                : array();
        } else {
            $devices = $this->deviceModel->findAll(array(), 'status ASC, device_name ASC');
        }
        $db      = Database::getInstance();
        $cmdLog  = $db->fetchAll(
            "SELECT dc.*, d.device_name, d.device_code, u.full_name as issued_by_name
             FROM device_commands dc
             LEFT JOIN devices d ON dc.device_id = d.id
             LEFT JOIN users u   ON dc.issued_by  = u.id
             ORDER BY dc.created_at DESC LIMIT 50"
        );
        // Operators get dedicated gate view
        if (Security::isOperator()) {
            Response::view('admin/operator_gate', compact('devices','cmdLog'), 'admin');
        } else {
            Response::view('admin/gate_override', compact('devices','cmdLog'), 'admin');
        }
    }

    public function sendCommand(): void {
        if (!Security::verifyCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
            Response::json(array('error' => 'Invalid CSRF token'), 403);
        }

        $deviceId = (int)($_POST['device_id'] ?? 0);
        $command  = Security::sanitize($_POST['command'] ?? '');

        $allowed = array('open_gate','close_gate','test_led_green','test_led_red','test_buzzer','reboot');
        if (!$deviceId || !in_array($command, $allowed)) {
            Response::json(array('error' => 'Invalid device or command'), 400);
        }

        $device = $this->deviceModel->find($deviceId);
        if (!$device) {
            Response::json(array('error' => 'Device not found'), 404);
        }
        if ($device['status'] !== 'online') {
            Response::json(array(
                'error'   => 'gate_offline',
                'message' => 'Gate <strong>' . htmlspecialchars($device['device_name']) . '</strong> is offline. Bring it online first.',
            ), 409);
        }

        // Expire any previous pending commands for same device+command
        $db = Database::getInstance();
        $db->execute(
            "UPDATE device_commands SET status='expired' WHERE device_id=? AND command=? AND status='pending'",
            array($deviceId, $command)
        );

        // Queue the new command - ESP32 picks it up on next heartbeat (every 3s)
        $db->execute(
            "INSERT INTO device_commands (device_id, command, params, status, issued_by, created_at)
             VALUES (?, ?, '[]', 'pending', ?, NOW())",
            array($deviceId, $command, $_SESSION['user_id'] ?? null)
        );

        // Update barrier status immediately in DB for visual feedback
        if ($command === 'open_gate')  $this->deviceModel->updateBarrierStatus($deviceId, 'open');
        if ($command === 'close_gate') $this->deviceModel->updateBarrierStatus($deviceId, 'closed');

        $label = str_replace('_', ' ', strtoupper($command));
        $this->logModel->write(
            'admin', 'warning',
            $_SESSION['user_id'] ?? null,
            $deviceId,
            'GATE_OVERRIDE',
            $label . ' queued for ' . $device['device_name'],
            Security::getIp()
        );

        Response::json(array(
            'success' => true,
            'message' => 'Command <strong>' . $label . '</strong> sent to <strong>' . htmlspecialchars($device['device_name']) . '</strong>. Executing now...',
            'command' => $command,
            'device'  => $device['device_name'],
        ));
    }

    public function gateStatus(): void {
        $this->deviceModel->markOfflineStale(2);
        $devices = $this->deviceModel->findAll(array(), 'device_name ASC');
        $out = array();
        foreach ($devices as $d) {
            $out[] = array(
                'id'             => $d['id'],
                'device_name'    => $d['device_name'],
                'device_code'    => $d['device_code'],
                'status'         => $d['status'],
                'barrier_status' => $d['barrier_status'],
                'last_heartbeat' => $d['last_heartbeat'],
                'ip_address'     => $d['ip_address'],
            );
        }
        Response::json($out);
    }
}
