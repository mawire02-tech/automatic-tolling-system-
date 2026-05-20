<?php
// app/helpers/Response.php

class Response {

    public static function json($data, int $code = 200): void {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function error(string $message = 'Error', int $code = 400, $errors = null): void {
        self::json(array('status'=>'error','message'=>$message,'errors'=>$errors), $code);
    }

    /**
     * Redirect to a path.
     * Always builds full URL using BASE_PATH so redirects work on XAMPP subfolders.
     */
    public static function redirect(string $path, bool $permanent = false): void {
        // If already a full URL, use as-is
        if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
            header('Location: ' . $path, true, $permanent ? 301 : 302);
            exit;
        }

        $base = defined('BASE_PATH') ? BASE_PATH : '';

        // Strip any existing base path prefix to avoid doubling
        if ($base !== '' && strpos($path, $base) === 0) {
            $path = substr($path, strlen($base));
        }

        // Strip /public prefix if already there
        if (strpos($path, '/public/') === 0) {
            $path = substr($path, 8); // remove /public/
        } elseif ($path === '/public') {
            $path = '/';
        }

        // Ensure leading slash
        if ($path === '' || $path[0] !== '/') {
            $path = '/' . $path;
        }

        // Build final URL: BASE_PATH + /public + /path
        $url = $base . '/public' . $path;

        header('Location: ' . $url, true, $permanent ? 301 : 302);
        exit;
    }

    public static function view(string $view, array $data = array(), string $layout = 'main'): void {
        extract($data);
        $layoutFile = APP_ROOT . "/app/views/layouts/{$layout}.php";
        $viewFile   = APP_ROOT . "/app/views/{$view}.php";

        if (!file_exists($viewFile)) {
            http_response_code(404);
            echo '<h2>View not found: ' . htmlspecialchars($view) . '</h2>';
            exit;
        }

        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        if (file_exists($layoutFile)) {
            require $layoutFile;
        } else {
            echo $content;
        }
        exit;
    }
}
