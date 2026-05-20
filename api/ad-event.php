<?php
declare(strict_types=1);

const AD_STATS_FILE = __DIR__ . '/../data/ad-stats.json';

function defaultAdStats(): array
{
    return [
        'totals' => [
            'impressions' => 0,
            'clicks' => 0,
        ],
        'songs' => [],
        'events' => [],
    ];
}

function readJsonBody(): array
{
    $body = file_get_contents('php://input');
    $data = json_decode($body ?: '{}', true);

    return is_array($data) ? $data : [];
}

function cleanText(mixed $value, int $limit = 180): string
{
    $text = trim((string) $value);
    $text = preg_replace('/[\x00-\x1F\x7F]/u', '', $text) ?? '';

    return substr($text, 0, $limit);
}

function readAdStats(): array
{
    $directory = dirname(AD_STATS_FILE);

    if (!is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    if (!file_exists(AD_STATS_FILE)) {
        file_put_contents(AD_STATS_FILE, json_encode(defaultAdStats(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n", LOCK_EX);
    }

    $stats = json_decode(file_get_contents(AD_STATS_FILE) ?: '{}', true);

    return is_array($stats) ? array_replace_recursive(defaultAdStats(), $stats) : defaultAdStats();
}

function writeAdStats(array $stats): void
{
    file_put_contents(AD_STATS_FILE, json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n", LOCK_EX);
}

function recordAdEvent(string $type): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'POST required']);
        return;
    }

    $payload = readJsonBody();
    $stats = readAdStats();
    $songTitle = cleanText($payload['song_title'] ?? $payload['songTitle'] ?? 'Unknown song');
    $songId = cleanText($payload['song_id'] ?? $payload['songId'] ?? $songTitle);
    $adUrl = cleanText($payload['ad_url'] ?? $payload['adUrl'] ?? '', 600);
    $referrer = cleanText($payload['referrer'] ?? ($_SERVER['HTTP_REFERER'] ?? ''), 600);
    $timestamp = cleanText($payload['timestamp'] ?? gmdate('c'), 80);
    $counter = $type === 'click' ? 'clicks' : 'impressions';

    $stats['totals'][$counter] = (int) ($stats['totals'][$counter] ?? 0) + 1;

    if (!isset($stats['songs'][$songId]) || !is_array($stats['songs'][$songId])) {
        $stats['songs'][$songId] = [
            'title' => $songTitle,
            'impressions' => 0,
            'clicks' => 0,
        ];
    }

    $stats['songs'][$songId]['title'] = $songTitle;
    $stats['songs'][$songId][$counter] = (int) ($stats['songs'][$songId][$counter] ?? 0) + 1;
    array_unshift($stats['events'], [
        'type' => $type,
        'song_id' => $songId,
        'song_title' => $songTitle,
        'timestamp' => $timestamp,
        'ad_url' => $adUrl,
        'referrer' => $referrer,
    ]);
    $stats['events'] = array_slice($stats['events'], 0, 500);

    writeAdStats($stats);
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
}
