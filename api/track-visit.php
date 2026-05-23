<?php
// api/track-visit.php — logs a page visit
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$dataFile = __DIR__ . '/../data/visits.json';

// Read existing data
$visits = file_exists($dataFile) ? json_decode((string) file_get_contents($dataFile), true) : [];
if (!is_array($visits)) $visits = [];

// Get today's date key
$today = date('Y-m-d');

// Get which page was visited
$page = isset($_POST['page']) ? basename((string) $_POST['page']) : 'index';
$page = preg_replace('/[^a-zA-Z0-9_-]/', '', $page); // sanitize
if ($page === '') $page = 'index';

// Initialize structure if needed
if (!isset($visits['daily']) || !is_array($visits['daily'])) $visits['daily'] = [];
if (!isset($visits['daily'][$today]) || !is_array($visits['daily'][$today])) $visits['daily'][$today] = ['total' => 0, 'pages' => []];
if (!isset($visits['daily'][$today]['pages']) || !is_array($visits['daily'][$today]['pages'])) $visits['daily'][$today]['pages'] = [];
if (!isset($visits['daily'][$today]['pages'][$page])) $visits['daily'][$today]['pages'][$page] = 0;

// Increment
$visits['daily'][$today]['total']++;
$visits['daily'][$today]['pages'][$page]++;

// Update totals
if (!isset($visits['allTime'])) $visits['allTime'] = 0;
$visits['allTime']++;

// Keep only last 90 days of daily data
$cutoff = date('Y-m-d', strtotime('-90 days'));
foreach ($visits['daily'] as $dateKey => $data) {
    if ($dateKey < $cutoff) unset($visits['daily'][$dateKey]);
}

// Save
file_put_contents($dataFile, json_encode($visits, JSON_PRETTY_PRINT), LOCK_EX);

echo json_encode(['ok' => true, 'total' => $visits['allTime']]);
