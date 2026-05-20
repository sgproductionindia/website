<?php
// Central config and DB connection
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Root directory (document root) — used for server-safe paths
$__root = rtrim($_SERVER['DOCUMENT_ROOT'] ?? __DIR__, '/');
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', $__root);
}

// Uploads dir (created automatically when needed)
if (!defined('UPLOADS_DIR')) {
    define('UPLOADS_DIR', ROOT_DIR . '/uploads');
}

// Database credentials — replace with real values or use environment variables
$db_host = getenv('DB_HOST') ?: '127.0.0.1';
$db_name = getenv('DB_NAME') ?: 'database_name';
$db_user = getenv('DB_USER') ?: 'db_user';
$db_pass = getenv('DB_PASS') ?: 'db_pass';

// Create uploads folder if missing
if (!is_dir(UPLOADS_DIR)) {
    @mkdir(UPLOADS_DIR, 0755, true);
}

// PDO connection — optional, will throw a readable error on failure
$pdo = null;
try {
    $dsn = "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4";
    $pdo = new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $e) {
    // Do not expose credentials; show a clear message
    http_response_code(500);
    echo 'Database connection error: ' . $e->getMessage();
    exit;
}

// Expose $pdo to including scripts
$GLOBALS['pdo'] = $pdo;
