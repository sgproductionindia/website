<?php
// Central config and optional DB connection
$appEnv = getenv('APP_ENV') ?: 'production';
if (!defined('APP_ENV')) {
    define('APP_ENV', $appEnv);
}

if (APP_ENV === 'production') {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

// Root directory (document root) — used for server-safe paths
$__root = rtrim($_SERVER['DOCUMENT_ROOT'] ?? __DIR__, '/');
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', $__root);
}

// Uploads dir (created automatically when needed)
if (!defined('UPLOADS_DIR')) {
    define('UPLOADS_DIR', ROOT_DIR . '/uploads');
}

// Create uploads folder if missing
if (!is_dir(UPLOADS_DIR)) {
    @mkdir(UPLOADS_DIR, 0755, true);
}

// PDO connection is optional. The current site/admin uses JSON files by default,
// so a missing MySQL server must not stop the admin panel from opening.
$pdo = null;
$db_host = getenv('DB_HOST') ?: '';
$db_name = getenv('DB_NAME') ?: '';
$db_user = getenv('DB_USER') ?: '';
$db_pass = getenv('DB_PASS') ?: '';

if ($db_host !== '' && $db_name !== '' && $db_user !== '') {
    try {
        $dsn = "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4";
        $pdo = new PDO($dsn, $db_user, $db_pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (Throwable $e) {
        if (getenv('DB_REQUIRED') === '1') {
            http_response_code(500);
            echo 'Database connection error. Please check server database settings.';
            exit;
        }

        error_log('Optional database connection failed: ' . $e->getMessage());
        $pdo = null;
    }
}

// Expose $pdo to including scripts
$GLOBALS['pdo'] = $pdo;
