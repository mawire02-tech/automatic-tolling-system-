<?php
// app/helpers/Security.php

class Security {

    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT, array('cost' => 12));
    }

    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }

    public static function generateToken($length = 32) {
        return bin2hex(random_bytes($length));
    }

    public static function generateRef($prefix = 'TXN') {
        return $prefix . date('Ymd') . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
    }

    public static function generateApiKey() {
        return 'ak_' . bin2hex(random_bytes(16));
    }

    public static function sanitize($input) {
        if (is_array($input)) return array_map(array('Security', 'sanitize'), $input);
        if (is_string($input)) return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return $input;
    }

    public static function sanitizeOutput($str) {
        return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public static function csrfToken() {
        if (empty($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = self::generateToken(32);
        }
        return $_SESSION[CSRF_TOKEN_NAME];
    }

    public static function verifyCsrf($token) {
        if (empty($token) || empty($_SESSION[CSRF_TOKEN_NAME])) return false;
        return hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
    }

    public static function isAuthenticated() {
        return !empty($_SESSION['user_id']) && !empty($_SESSION['user_role']);
    }

    public static function getRole() {
        return $_SESSION['user_role'] ?? '';
    }

    public static function isAdmin() {
        return self::getRole() === 'admin';
    }

    public static function isOperator() {
        return self::getRole() === 'operator';
    }

    public static function isUser() {
        return self::getRole() === 'user';
    }

    /**
     * Require user to be logged in.
     * Redirects to login if not authenticated.
     */
    public static function requireAuth($role = null) {
        if (!self::isAuthenticated()) {
            Response::redirect('/login');
        }
        if ($role !== null && self::getRole() !== $role && self::getRole() !== 'admin') {
            self::denyAccess();
        }
    }

    /**
     * Require admin OR operator role.
     * Used for admin panel pages.
     */
    public static function requireAdmin() {
        if (!self::isAuthenticated()) {
            Response::redirect('/login');
        }
        if (!in_array(self::getRole(), array('admin', 'operator'))) {
            self::denyAccess();
        }
    }

    /**
     * Require strictly admin role only (not operator).
     * Used for sensitive actions: user management, settings, etc.
     */
    public static function requireStrictAdmin() {
        if (!self::isAuthenticated()) {
            Response::redirect('/login');
        }
        if (self::getRole() !== 'admin') {
            self::denyAccess();
        }
    }

    /**
     * Check if current user can access a page, redirect if not.
     * Operator: only dashboard + gate-override
     * Admin: everything
     * User: only /user/* pages
     */
    public static function checkPageAccess(string $page) {
        $role = self::getRole();

        if ($role === 'admin') return; // admin sees all

        if ($role === 'operator') {
            $allowed = array('admin/dashboard', 'admin/gate-override');
            foreach ($allowed as $p) {
                if (strpos($page, $p) !== false) return;
            }
            self::denyAccess();
        }

        if ($role === 'user') {
            if (strpos($page, 'user/') !== false) return;
            Response::redirect('/user/dashboard');
        }

        Response::redirect('/login');
    }

    private static function denyAccess() {
        http_response_code(403);
        $bp = defined('BASE_PATH') ? BASE_PATH : '';
        echo '<!DOCTYPE html><html><body style="font-family:monospace;padding:40px;background:#0a0a0f;color:#e0e0e0">'
           . '<h2 style="color:#ff3d5a">403 — Access Denied</h2>'
           . '<p>You do not have permission to access this page.</p>'
           . '<a href="' . $bp . '/public/' . (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'user' ? 'user/dashboard' : 'admin/dashboard') . '" style="color:#00d4ff">&larr; Back to Dashboard</a>'
           . '</body></html>';
        exit;
    }

    public static function getIp() {
        foreach (array('HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR') as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = trim(explode(',', $_SERVER[$key])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
            }
        }
        return '127.0.0.1';
    }

    public static function validateApiKey($key) {
        if (empty($key)) return null;
        $db = Database::getInstance();
        return $db->fetchOne("SELECT * FROM devices WHERE api_key = ? LIMIT 1", array($key)) ?: null;
    }

    /**
     * Get currency symbol from DB settings (cached in session).
     */
    public static function currency() {
        if (!empty($_SESSION['currency_symbol'])) return $_SESSION['currency_symbol'];
        try {
            $db  = Database::getInstance();
            $row = $db->fetchOne("SELECT setting_value FROM system_settings WHERE setting_key = 'currency_symbol' LIMIT 1");
            $sym = $row ? $row['setting_value'] : '$';
        } catch (Exception $e) {
            $sym = '$';
        }
        $_SESSION['currency_symbol'] = $sym;
        return $sym;
    }

    /**
     * Clear cached currency so it reloads after settings change.
     */
    public static function clearCurrencyCache() {
        unset($_SESSION['currency_symbol']);
    }
}
