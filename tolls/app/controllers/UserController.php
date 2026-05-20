<?php
// app/controllers/UserController.php

class UserController {

    private Database         $db;
    private UserModel        $userModel;
    private TransactionModel $txModel;
    private TopupModel       $topupModel;
    private SettingModel     $settingModel;
    private LogModel         $logModel;

    public function __construct() {
        Security::requireAuth();
        $this->db           = Database::getInstance();
        $this->userModel    = new UserModel();
        $this->txModel      = new TransactionModel();
        $this->topupModel   = new TopupModel();
        $this->settingModel = new SettingModel();
        $this->logModel     = new LogModel();
    }

    public function dashboard(): void {
        $userId = (int)$_SESSION['user_id'];
        $user   = $this->userModel->find($userId);

        $vehicles = $this->db->fetchAll(
            "SELECT v.*, rc.card_uid, rc.status AS card_status
             FROM vehicles v
             LEFT JOIN rfid_cards rc ON v.id = rc.vehicle_id
             WHERE v.user_id = ? AND v.status = 'active'",
            [$userId]
        );
        $recentTx = $this->db->fetchAll(
            "SELECT t.*, d.device_name, v.plate_number
             FROM transactions t
             LEFT JOIN devices d  ON t.device_id  = d.id
             LEFT JOIN vehicles v ON t.vehicle_id = v.id
             WHERE t.user_id = ? ORDER BY t.processed_at DESC LIMIT 10",
            [$userId]
        );
        $monthRevenue  = (float)$this->db->fetchOne(
            "SELECT COALESCE(SUM(toll_amount),0) as r FROM transactions
             WHERE user_id=? AND MONTH(processed_at)=MONTH(CURDATE()) AND YEAR(processed_at)=YEAR(CURDATE()) AND status='success'",
            [$userId]
        )['r'];
        $totalTrips    = (int)$this->db->fetchOne(
            "SELECT COUNT(*) as c FROM transactions WHERE user_id=? AND status='success'",
            [$userId]
        )['c'];
        $pendingTopups = $this->db->fetchAll(
            "SELECT * FROM topup_requests WHERE user_id=? ORDER BY requested_at DESC LIMIT 5",
            [$userId]
        );
        $weeklySpend   = $this->db->fetchAll(
            "SELECT DATE(processed_at) as date, SUM(toll_amount) as amount
             FROM transactions
             WHERE user_id=? AND status='success' AND processed_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
             GROUP BY DATE(processed_at) ORDER BY date",
            [$userId]
        );

        $threshold   = (float)$this->settingModel->get('low_balance_alert', 50);
        $lowBalance  = (float)$user['wallet_balance'] < $threshold;

        // Load unread alerts/notifications for this user
        $userAlerts = $this->db->fetchAll(
            "SELECT * FROM notifications WHERE (user_id = ? OR user_id IS NULL) AND is_read = 0
             ORDER BY created_at DESC LIMIT 10",
            array($userId)
        );

        Response::view('user/dashboard', compact(
            'user','vehicles','recentTx','monthRevenue','totalTrips',
            'pendingTopups','weeklySpend','lowBalance','threshold','userAlerts'
        ), 'user');
    }

    public function transactions(): void {
        $userId   = (int)$_SESSION['user_id'];
        $dateFrom = Security::sanitize($_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days')));
        $dateTo   = Security::sanitize($_GET['date_to']   ?? date('Y-m-d'));
        $page     = max(1, (int)($_GET['page'] ?? 1));
        $limit    = 20;

        $result = $this->txModel->getPaginated('', $dateFrom, $dateTo, '', 0, $userId, $page, $limit);

        Response::view('user/transactions', [
            'txs'       => $result['data'],
            'total'     => $result['total'],
            'totalSpent'=> $result['revenue'],
            'page'      => $page,
            'limit'     => $limit,
            'dateFrom'  => $dateFrom,
            'dateTo'    => $dateTo,
        ], 'user');
    }

    public function vehicles(): void {
        $userId   = (int)$_SESSION['user_id'];
        $vehicles = $this->db->fetchAll(
            "SELECT v.*, rc.card_uid, rc.status AS card_status
             FROM vehicles v
             LEFT JOIN rfid_cards rc ON v.id = rc.vehicle_id
             WHERE v.user_id = ?",
            [$userId]
        );
        Response::view('user/vehicles', compact('vehicles'), 'user');
    }

    public function wallet(): void {
        $userId    = (int)$_SESSION['user_id'];
        $user      = $this->userModel->find($userId);
        $topups    = $this->db->fetchAll(
            "SELECT * FROM topup_requests WHERE user_id=? ORDER BY requested_at DESC LIMIT 20",
            [$userId]
        );
        $threshold = (float)$this->settingModel->get('low_balance_alert', 50);
        Response::view('user/wallet', compact('user','topups','threshold'), 'user');
    }

    public function requestTopup(): void {
        if (!Security::verifyCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) Response::error('Invalid CSRF', 403);
        $userId = (int)$_SESSION['user_id'];
        $data   = Security::sanitize($_POST);

        $v = new Validator();
        $v->validate($data, [
            'amount'         => 'required|numeric|min_val:5|max:100',
            'payment_method' => 'required|in:bank_transfer,cash,ecocash,onemoney',
            'reference_number' => 'required|max:100|alpha_num|min:5',
        ]);
        if ($v->fails()) Response::json(['error' => $v->firstError()], 400);

        if ($this->topupModel->hasPending($userId)) {
            Response::json(['error' => 'You already have a pending top-up request.'], 400);
        }

        $ref = Security::generateRef('TOP');
        $this->db->execute(
            "INSERT INTO topup_requests (user_id, request_ref, amount, payment_method, reference_number, status)
             VALUES (?, ?, ?, ?, ?, 'pending')",
            [$userId, $ref, $data['amount'], $data['payment_method'], $data['reference_number'] ?? '']
        );
        $this->logModel->write('transaction','info',$userId,null,'TOPUP_REQUESTED',"Top-up " . $data['amount'] . " requested",Security::getIp());
        Response::json(['success' => true, 'message' => "Top-up request of " . $data['amount'] . " submitted. Awaiting admin approval.", 'ref' => $ref]);
    }

    public function profile(): void {
        $userId = (int)$_SESSION['user_id'];
        $user   = $this->userModel->find($userId);
        Response::view('user/profile', compact('user'), 'user');
    }


    public function markAlert(): void {
        // JS posts csrf token via FormData with the CSRF_TOKEN_NAME key
        $token = $_POST[CSRF_TOKEN_NAME] ?? '';
        if (!Security::verifyCsrf($token)) {
            Response::json(array('success' => false, 'message' => 'Invalid CSRF token'), 403);
            return;
        }

        $userId = (int)$_SESSION['user_id'];
        $id     = (int)($_POST['id'] ?? 0);

        if ($id === 0) {
            // Mark ALL unread notifications for this user as read
            $this->db->execute(
                "UPDATE notifications SET is_read = 1
                 WHERE (user_id = ? OR user_id IS NULL) AND is_read = 0",
                array($userId)
            );
        } else {
            // Mark single notification as read (must belong to user or be broadcast)
            $this->db->execute(
                "UPDATE notifications SET is_read = 1
                 WHERE id = ? AND (user_id = ? OR user_id IS NULL) AND is_read = 0",
                array($id, $userId)
            );
        }

        Response::json(array('success' => true));
    }

    public function updateProfile(): void {
        if (!Security::verifyCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) Response::error('Invalid CSRF', 403);
        $userId = (int)$_SESSION['user_id'];
        $data   = Security::sanitize($_POST);

        $v = new Validator();
        $v->validate($data, ['full_name' => 'required|max:100|regex:/^[a-zA-ZÀ-ÖØ-öø-ÿ\s]+$/', 'email' => 'required|email', 'phone' => 'max:20|numeric|phone|regex:/^\+?[0-9\s\-]*$/']);
        if ($v->fails()) Response::json(['error' => $v->firstError()], 400);

        $this->userModel->update($userId, [
            'full_name' => $data['full_name'],
            'email'     => $data['email'],
            'phone'     => $data['phone'] ?? '',
        ]);

        if (!empty($_POST['new_password'])) {
            $user = $this->userModel->find($userId);
            if (!Security::verifyPassword($_POST['current_password'] ?? '', $user['password_hash'])) {
                Response::json(['error' => 'Current password is incorrect.'], 400);
            }
            $v2 = new Validator();
            $v2->validate(['password' => $_POST['new_password']], ['password' => 'required|password']);
            if ($v2->fails()) Response::json(['error' => $v2->firstError()], 400);
            $this->userModel->update($userId, ['password_hash' => Security::hashPassword($_POST['new_password'])]);
        }

        $_SESSION['full_name'] = $data['full_name'];
        Response::json(['success' => true, 'message' => 'Profile updated successfully.']);
    }
}
