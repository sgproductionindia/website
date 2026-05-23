<?php
// api/get-visits.php — returns visit statistics
header('Content-Type: application/json');

$dataFile = __DIR__ . '/../data/visits.json';
$visits = file_exists($dataFile) ? json_decode((string) file_get_contents($dataFile), true) : [];
if (!is_array($visits)) $visits = [];

$allTime = $visits['allTime'] ?? 0;
$daily = is_array($visits['daily'] ?? null) ? $visits['daily'] : [];

// Calculate period totals
$today = date('Y-m-d');
$todayCount = $daily[$today]['total'] ?? 0;

$last7 = 0;
$last30 = 0;
for ($i = 0; $i < 30; $i++) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $count = $daily[$d]['total'] ?? 0;
    $last30 += $count;
    if ($i < 7) $last7 += $count;
}

// Previous 7 days for comparison
$prev7 = 0;
for ($i = 7; $i < 14; $i++) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $prev7 += $daily[$d]['total'] ?? 0;
}
$change7 = $prev7 > 0 ? round(($last7 - $prev7) / $prev7 * 100) : 0;

// Daily breakdown for chart (last 14 days)
$chartData = [];
for ($i = 13; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $chartData[] = [
        'date' => $d,
        'label' => date('M j', strtotime($d)),
        'count' => $daily[$d]['total'] ?? 0
    ];
}

// Top pages
$pageStats = [];
foreach ($daily as $date => $data) {
    if ($date < date('Y-m-d', strtotime('-30 days'))) continue;
    foreach (($data['pages'] ?? []) as $page => $count) {
        if (!isset($pageStats[$page])) $pageStats[$page] = 0;
        $pageStats[$page] += $count;
    }
}
arsort($pageStats);

echo json_encode([
    'allTime' => $allTime,
    'today' => $todayCount,
    'last7' => $last7,
    'last30' => $last30,
    'change7' => $change7,
    'chart' => $chartData,
    'topPages' => array_slice($pageStats, 0, 5, true)
]);
