<?php
// config/app.php

define('APP_NAME',    'SmartToll PRO');
define('APP_VERSION', '2.4.0');
define('APP_ROOT',    dirname(__DIR__));

// ── Reliable BASE_PATH for XAMPP ─────────────────────────────
// Detects the subfolder path (e.g. /tolls) regardless of .htaccess rewriting
if (!defined('BASE_PATH')) {
    // APP_ROOT is the tolls/ directory (one level up from config/)
    // We need the web path to tolls/ relative to the document root
    $docRoot  = isset($_SERVER['DOCUMENT_ROOT']) ? rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/') : '';
    $appRoot  = rtrim(str_replace('\\', '/', APP_ROOT), '/');

    if ($docRoot && strpos($appRoot, $docRoot) === 0) {
        // APP_ROOT is inside DOCUMENT_ROOT — extract the relative path
        $base = substr($appRoot, strlen($docRoot));
    } else {
        // Fallback: parse from SCRIPT_NAME
        $script = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', $_SERVER['SCRIPT_NAME']) : '/index.php';
        // Remove /public/index.php or /index.php
        $base = preg_replace('#/(public/)?index\.php$#', '', $script);
        $base = preg_replace('#/public$#', '', $base);
    }

    // Normalize: must not end with /
    $base = rtrim($base, '/');
    // Must start with / or be empty
    if ($base !== '' && $base[0] !== '/') $base = '/' . $base;

    define('BASE_PATH', $base);
}

define('APP_URL',     'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE_PATH);
define('UPLOAD_PATH', APP_ROOT . '/public/uploads/');

define('CSRF_TOKEN_NAME',    '_csrf_token');
define('SESSION_LIFETIME',   7200);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_DURATION',   900);
define('API_RATE_LIMIT',     60);
define('API_VERSION',        'v1');

date_default_timezone_set('Africa/Harare');

ini_set('display_errors', 1);
ini_set('log_errors',     1);
error_reporting(E_ALL);

ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure',   0);
ini_set('session.use_strict_mode', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Autoloader
spl_autoload_register(function ($class) {
    $paths = array(
        APP_ROOT . '/app/controllers/',
        APP_ROOT . '/app/models/',
        APP_ROOT . '/app/helpers/',
    );
    foreach ($paths as $path) {
        $file = $path . $class . '.php';
        if (file_exists($file)) { require_once $file; return; }
    }
});

require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/app/helpers/Security.php';
require_once APP_ROOT . '/app/helpers/Validator.php';
require_once APP_ROOT . '/app/helpers/Response.php';
require_once APP_ROOT . '/app/models/BaseModel.php';

// URL helpers
function asset($path) {
    return BASE_PATH . '/public/' . ltrim($path, '/');
}
function url($path) {
    return BASE_PATH . '/public/' . ltrim($path, '/');
}
