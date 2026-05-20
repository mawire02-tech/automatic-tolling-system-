<?php
// app/controllers/AuthController.php

class AuthController {

    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function showLogin(): void {
        if (Security::isAuthenticated()) {
            $role = Security::getRole();
            if ($role === 'admin' || $role === 'operator') {
                Response::redirect('/admin/dashboard');
            } else {
                Response::redirect('/user/dashboard');
            }
        }
        Response::view('auth/login', array('title' => 'Login', 'csrf' => Security::csrfToken()), 'auth');
    }

    public function login(): void {
        if (!Security::verifyCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
            Response::view('auth/login', array('error' => 'Invalid request.', 'csrf' => Security::csrfToken()), 'auth');
            return;
        }

        $username = Security::sanitize($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $ip       = Security::getIp();

        $user = $this->db->fetchOne(
            "SELECT * FROM users WHERE (username = ? OR email = ?) LIMIT 1",
            array($username, $username)
        );

        if (!$user) {
            $this->log('auth', 'warning', null, 'LOGIN_FAILED', "Unknown user: $username", $ip);
            Response::view('auth/login', array('error' => 'Invalid credentials.', 'csrf' => Security::csrfToken()), 'auth');
            return;
        }

        // Operator pending approval
        if ($user['role'] === 'operator' && $user['status'] === 'pending') {
            Response::view('auth/login', array(
                'error' => 'Your operator account is pending admin approval. Please wait.',
                'csrf'  => Security::csrfToken()
            ), 'auth');
            return;
        }

        // Regular pending (user registered but not activated)
        if ($user['status'] === 'pending') {
            Response::view('auth/login', array(
                'error' => 'Account pending activation.',
                'csrf'  => Security::csrfToken()
            ), 'auth');
            return;
        }

        // Suspended
        if ($user['status'] === 'suspended') {
            Response::view('auth/login', array(
                'error' => 'Account suspended. Contact admin.',
                'csrf'  => Security::csrfToken()
            ), 'auth');
            return;
        }

        // Lockout
        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            $rem = ceil((strtotime($user['locked_until']) - time()) / 60);
            Response::view('auth/login', array(
                'error' => "Account locked. Try again in $rem minute(s).",
                'csrf'  => Security::csrfToken()
            ), 'auth');
            return;
        }

        if (!Security::verifyPassword($password, $user['password_hash'])) {
            $attempts = (int)$user['login_attempts'] + 1;
            if ($attempts >= MAX_LOGIN_ATTEMPTS) {
                $locked = date('Y-m-d H:i:s', time() + LOCKOUT_DURATION);
                $this->db->execute("UPDATE users SET login_attempts = 0, locked_until = ? WHERE id = ?", array($locked, $user['id']));
            } else {
                $this->db->execute("UPDATE users SET login_attempts = ? WHERE id = ?", array($attempts, $user['id']));
            }
            $this->log('auth', 'warning', $user['id'], 'LOGIN_FAILED', "Wrong password for {$user['username']}", $ip);
            Response::view('auth/login', array('error' => 'Invalid credentials.', 'csrf' => Security::csrfToken()), 'auth');
            return;
        }

        // Success
        $this->db->execute(
            "UPDATE users SET login_attempts = 0, locked_until = NULL, last_login = NOW() WHERE id = ?",
            array($user['id'])
        );

        $_SESSION['user_id']    = $user['id'];
        $_SESSION['user_role']  = $user['role'];
        $_SESSION['username']   = $user['username'];
        $_SESSION['full_name']  = $user['full_name'];
        $_SESSION['login_time'] = time();
        // Clear cached currency to force reload
        Security::clearCurrencyCache();
        session_regenerate_id(true);

        $this->log('auth', 'info', $user['id'], 'LOGIN_SUCCESS', "User {$user['username']} logged in", $ip);

        $role = $user['role'];
        if ($role === 'admin' || $role === 'operator') {
            Response::redirect('/admin/dashboard');
        } else {
            Response::redirect('/user/dashboard');
        }
    }

    public function logout(): void {
        $uid = $_SESSION['user_id'] ?? null;
        $this->log('auth', 'info', $uid, 'LOGOUT', 'User logged out', Security::getIp());
        session_destroy();
        Response::redirect('/login');
    }

    public function showRegister(): void {
        Response::view('auth/register', array('title' => 'Register', 'csrf' => Security::csrfToken()), 'auth');
    }

    public function register(): void {
        if (!Security::verifyCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
            Response::json(array('error' => 'Invalid CSRF token'), 403);
        }

        $data = Security::sanitize($_POST);
        $requestedRole = $data['role'] ?? 'user';

        // Only allow user or operator self-registration
        if (!in_array($requestedRole, array('user', 'operator'))) {
            $requestedRole = 'user';
        }

        $v = new Validator();
        $v->validate($data, array(
            'full_name' => 'required|min:3|max:100|alpha_num',
            'username'  => 'required|min:3|max:50|alpha_num',
            'email'     => 'required|email|max:100',
            'password'  => 'required|password',
            'phone'     => 'max:20|numeric|phone',
        ));
        if ($v->fails()) {
            Response::view('auth/register', array('errors' => $v->errors(), 'old' => $data, 'csrf' => Security::csrfToken()), 'auth');
            return;
        }

        // Check duplicate
        $existing = $this->db->fetchOne(
            "SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1",
            array($data['username'], $data['email'])
        );
        if ($existing) {
            Response::view('auth/register', array('error' => 'Username or email already exists.', 'old' => $data, 'csrf' => Security::csrfToken()), 'auth');
            return;
        }

        // Operators start as 'pending' — need admin approval
        // Users start as 'active'
        $status = ($requestedRole === 'operator') ? 'pending' : 'active';

        $this->db->execute(
            "INSERT INTO users (username, email, password_hash, full_name, phone, role, status) VALUES (?,?,?,?,?,?,?)",
            array(
                $data['username'],
                $data['email'],
                Security::hashPassword($_POST['password']),
                $data['full_name'],
                $data['phone'] ?? '',
                $requestedRole,
                $status,
            )
        );

        $this->log('auth', 'info', null, 'REGISTER', "New {$requestedRole} registered: {$data['username']}", Security::getIp());

        if ($requestedRole === 'operator') {
            Response::view('auth/login', array(
                'success' => 'Operator account created! Awaiting admin approval before you can log in.',
                'csrf'    => Security::csrfToken()
            ), 'auth');
        } else {
            Response::view('auth/login', array(
                'success' => 'Account created! You can now log in.',
                'csrf'    => Security::csrfToken()
            ), 'auth');
        }
    }

    private function log($type, $severity, $userId, $action, $desc, $ip = '') {
        $this->db->execute(
            "INSERT INTO system_logs (log_type, severity, user_id, action, description, ip_address) VALUES (?,?,?,?,?,?)",
            array($type, $severity, $userId, $action, $desc, $ip)
        );
    }
}
