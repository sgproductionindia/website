<?php
declare(strict_types=1);

require_once rtrim($_SERVER['DOCUMENT_ROOT'] ?? __DIR__, '/') . '/config.php';

$dataDir = (defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__)) . '/data';
if (!is_dir($dataDir)) {
    @mkdir($dataDir, 0755, true);
}

$tracksFile = $dataDir . '/tracks.json';
$settingsFile = $dataDir . '/settings.json';

$pdo = $GLOBALS['pdo'] ?? null;

$tracks = [];
$settings = [];

if ($pdo instanceof PDO) {
    try {
        // Try a sensible tracks query — adapt your schema if different
        $stmt = $pdo->query('SELECT * FROM tracks ORDER BY id DESC LIMIT 1000');
        if ($stmt !== false) {
            $tracks = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
    } catch (Throwable $e) {
        // Table missing or other DB issue — leave tracks as empty
        $tracks = [];
    }

    try {
        // Attempt to read settings as key/value table; fall back to full rows
        $stmt = $pdo->query('SELECT * FROM settings');
        if ($stmt !== false) {
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            // If settings table has key/value columns, normalize
            if (!empty($rows) && isset($rows[0]['key']) && isset($rows[0]['value'])) {
                foreach ($rows as $r) {
                    $settings[$r['key']] = $r['value'];
                }
            } else {
                $settings = $rows;
            }
        }
    } catch (Throwable $e) {
        $settings = [];
    }
}

// Write to files (safe write)
file_put_contents($tracksFile, json_encode($tracks, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n", LOCK_EX);
file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n", LOCK_EX);

header('Content-Type: application/json');
echo json_encode(['ok' => true, 'tracks' => count($tracks), 'settings' => is_array($settings) ? count($settings) : 0]);
