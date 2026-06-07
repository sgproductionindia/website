<?php
declare(strict_types=1);

require_once rtrim($_SERVER['DOCUMENT_ROOT'] ?? __DIR__, '/') . '/config.php';

define('TRACKS_FILE', (defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__)) . '/data/tracks.json');
define('LIKES_FILE', (defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__)) . '/data/likes.json');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function sgLikesSlugify(string $value): string
{
    $slug = strtolower(trim($value));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    $slug = trim($slug, '-');

    return $slug !== '' ? $slug : 'track';
}

function sgLikesReadJson(string $file, mixed $fallback): mixed
{
    if (!file_exists($file)) {
        return $fallback;
    }

    $decoded = json_decode(file_get_contents($file) ?: '', true);

    return is_array($decoded) ? $decoded : $fallback;
}

function sgLikesTrackKeys(array $track): array
{
    $keys = [
        (string) ($track['id'] ?? ''),
        (string) ($track['slug'] ?? ''),
        sgLikesSlugify((string) ($track['slug'] ?? '')),
        sgLikesSlugify((string) ($track['title'] ?? '')),
    ];

    return array_values(array_unique(array_filter($keys, static fn (string $key): bool => $key !== '')));
}

function sgLikesStoredCount(array $store, array $keys): int
{
    $best = 0;

    foreach ($store as $key => $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $aliases = is_array($entry['aliases'] ?? null) ? $entry['aliases'] : [];
        $entryKeys = array_values(array_unique(array_merge([(string) $key], array_map('strval', $aliases))));

        if (array_intersect($keys, $entryKeys) !== []) {
            $best = max($best, (int) ($entry['likes'] ?? $entry['likeCount'] ?? 0));
        }
    }

    return $best;
}

$tracks = sgLikesReadJson(TRACKS_FILE, []);
$store = sgLikesReadJson(LIKES_FILE, []);
$perTrack = [];
$total = 0;

foreach ($tracks as $track) {
    if (!is_array($track)) {
        continue;
    }

    $keys = sgLikesTrackKeys($track);
    $id = (string) ($track['id'] ?? ($keys[0] ?? ''));

    if ($id === '') {
        continue;
    }

    $likes = max(
        (int) ($track['likes'] ?? $track['likeCount'] ?? 0),
        sgLikesStoredCount($store, $keys)
    );

    $perTrack[] = [
        'id' => $id,
        'slug' => (string) ($track['slug'] ?? ''),
        'title' => (string) ($track['title'] ?? ''),
        'likes' => $likes,
    ];
    $total += $likes;
}

echo json_encode([
    'ok' => true,
    'total' => $total,
    'tracks' => $perTrack,
], JSON_UNESCAPED_SLASHES) . "\n";
