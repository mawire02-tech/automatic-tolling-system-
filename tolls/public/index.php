<?php
require_once dirname(__DIR__) . '/config/app.php';

// Get clean URI
$uri    = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
$method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';

// Remove query string
$pos = strpos($uri, '?');
if ($pos !== false) {
    $uri = substr($uri, 0, $pos);
}

// Strip BASE_PATH prefix (e.g. /tolls)
if (BASE_PATH !== '' && strpos($uri, BASE_PATH) === 0) {
    $uri = substr($uri, strlen(BASE_PATH));
}

// Strip /public prefix
if (strpos($uri, '/public') === 0) {
    $uri = substr($uri, 7);
}

// Default to /
if ($uri === '' || $uri === false || $uri === null) {
    $uri = '/';
}

// Ensure leading slash
if (substr($uri, 0, 1) !== '/') {
    $uri = '/' . $uri;
}

$routes = array(
    'GET' => array(
        '/'                           => array('AuthController',         'showLogin'),
        '/login'                      => array('AuthController',         'showLogin'),
        '/logout'                     => array('AuthController',         'logout'),
        '/register'                   => array('AuthController',         'showRegister'),
        '/admin/dashboard'            => array('AdminController',        'dashboard'),
        '/admin/users'                => array('AdminController',        'users'),
        '/admin/vehicles'             => array('AdminController',        'vehicles'),
        '/admin/transactions'         => array('AdminController',        'transactions'),
        '/admin/topups'               => array('AdminController',        'topups'),
        '/admin/devices'              => array('AdminController',        'devices'),
        '/admin/logs'                 => array('AdminController',        'logs'),
        '/admin/maintenance'          => array('MaintenanceController',  'index'),
        '/admin/settings'             => array('AdminController',        'settings'),
        '/admin/reports'              => array('ReportsController',      'index'),
        '/admin/ai-insights'          => array('RuleEngineController',   'index'),
        '/admin/forecast'             => array('ForecastController',     'index'),
        '/admin/blacklist'            => array('BlacklistController',    'index'),
        '/admin/shifts'               => array('ShiftController',        'index'),
        '/admin/alerts'               => array('NotificationController', 'index'),
        '/admin/notifications'        => array('NotificationController', 'index'),
        '/admin/kiosk'                => array('KioskController',        'index'),
        '/admin/gate-override'        => array('GateOverrideController', 'index'),
        '/admin/api/stats'            => array('AdminController',        'apiStats'),
        '/admin/api/gate-status'      => array('GateOverrideController', 'gateStatus'),
        '/user/dashboard'             => array('UserController',         'dashboard'),
        '/user/transactions'          => array('UserController',         'transactions'),
        '/user/vehicles'              => array('UserController',         'vehicles'),
        '/user/wallet'                => array('UserController',         'wallet'),
        '/user/profile'               => array('UserController',         'profile'),
    ),
    'POST' => array(
        '/login'                       => array('AuthController',         'login'),
        '/register'                    => array('AuthController',         'register'),
        '/admin/users/save'            => array('AdminController',        'saveUser'),
        '/admin/users/operator'        => array('AdminController',        'approveOperator'),
        '/admin/vehicles/save'         => array('AdminController',        'saveVehicle'),
        '/admin/vehicles/delete'       => array('AdminController',        'deleteVehicle'),   // ← NEW
        '/admin/topups/process'        => array('AdminController',        'approveTopup'),
        '/admin/devices/save'          => array('AdminController',        'saveDevice'),
        '/admin/assign-gate'           => array('AdminController',        'assignGate'),
        '/admin/settings/save'         => array('AdminController',        'saveSettings'),
        '/admin/maintenance/run'       => array('MaintenanceController',  'runAction'),
        '/admin/blacklist/save'        => array('BlacklistController',    'save'),
        '/admin/blacklist/remove'      => array('BlacklistController',    'remove'),
        '/admin/shifts/checkin'        => array('ShiftController',        'checkin'),
        '/admin/shifts/checkout'       => array('ShiftController',        'checkout'),
        '/admin/notifications/send'    => array('NotificationController', 'send'),
        '/admin/notifications/mark'    => array('NotificationController', 'markRead'),
        '/admin/kiosk/topup'           => array('KioskController',        'processTopup'),
        '/admin/gate-override/command' => array('GateOverrideController', 'sendCommand'),
        '/user/alerts/mark'            => array('UserController',         'markAlert'),
        '/user/topup/request'          => array('UserController',         'requestTopup'),
        '/user/profile/update'         => array('UserController',         'updateProfile'),
        '/api/v1/toll/process'         => array('TollController',         'processRFID'),
        '/api/v1/device/heartbeat'     => array('TollController',         'heartbeat'),
        '/api/v1/sync'                 => array('TollController',         'syncOffline'),
        '/api/v1/device/commands'      => array('TollController',         'pollCommands'),
    ),
);

$methodRoutes = isset($routes[$method]) ? $routes[$method] : array();

if (isset($methodRoutes[$uri])) {
    $controllerName = $methodRoutes[$uri][0];
    $action         = $methodRoutes[$uri][1];
    require_once APP_ROOT . '/app/controllers/' . $controllerName . '.php';
    $controller = new $controllerName();
    $controller->$action();
} else {
    http_response_code(404);
    echo '<!DOCTYPE html><html><body style="font-family:monospace;padding:40px;background:#0a0a0f;color:#e0e0e0">';
    echo '<h2>404 &mdash; Not Found</h2>';
    echo '<p>URI received: <code>' . htmlspecialchars($uri) . '</code></p>';
    echo '<p>Method: <code>' . htmlspecialchars($method) . '</code></p>';
    echo '<p>BASE_PATH: <code>' . htmlspecialchars(BASE_PATH) . '</code></p>';
    echo '<p><a href="' . htmlspecialchars(BASE_PATH) . '/public/?r=login" style="color:#00d4ff">Try login via ?r= routing</a></p>';
    echo '</body></html>';
}