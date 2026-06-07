<?php
declare(strict_types=1);

require_once rtrim($_SERVER['DOCUMENT_ROOT'] ?? __DIR__, '/') . '/config.php';

define('TRACKS_FILE', (defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__)) . '/data/tracks.json');
define('LIKES_FILE', (defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__)) . '/data/likes.json');

header('Content-Type: application/json; charset=utf-8');

function cleanText(mixed $value, int $limit = 180): string
{
    $text = trim((string) $value);
    $text = preg_replace('/[\x00-\x1F\x7F]/u', '', $text) ?? '';

    return substr($text, 0, $limit);
}

function slugify(string $value): string
{
    $slug = strtolower(trim($value));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    $slug = trim($slug, '-');

    return $slug !== '' ? $slug : 'track';
}

function respond(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES) . "\n";
}

function prepareTracksFile(): void
{
    $directory = dirname(TRACKS_FILE);

    if (!is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    if (!file_exists(TRACKS_FILE)) {
        file_put_contents(TRACKS_FILE, "[]\n", LOCK_EX);
    }
}

function readLikesStore(): array
{
    if (!file_exists(LIKES_FILE)) {
        return [];
    }

    $store = json_decode(file_get_contents(LIKES_FILE) ?: '{}', true);

    return is_array($store) ? $store : [];
}

function writeLikesStore(array $store): void
{
    $directory = dirname(LIKES_FILE);

    if (!is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    file_put_contents(
        LIKES_FILE,
        json_encode($store, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
        LOCK_EX
    );
}

function trackLikeKeys(array $track, string $inputId = ''): array
{
    $keys = [
        $inputId,
        (string) ($track['id'] ?? ''),
        (string) ($track['slug'] ?? ''),
        slugify((string) ($track['slug'] ?? '')),
        slugify((string) ($track['title'] ?? '')),
    ];

    return array_values(array_unique(array_filter($keys, static fn (string $key): bool => $key !== '')));
}

function findStoredLikeEntry(array $store, array $keys): ?array
{
    foreach ($store as $key => $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $aliases = is_array($entry['aliases'] ?? null) ? $entry['aliases'] : [];
        $candidateKeys = array_values(array_unique(array_merge([(string) $key], array_map('strval', $aliases))));

        if (array_intersect($keys, $candidateKeys) !== []) {
            return $entry;
        }
    }

    return null;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['ok' => false, 'error' => 'Method not allowed.'], 405);
    exit;
}

$jsonBody = json_decode(file_get_contents('php://input') ?: '[]', true);
$payload = is_array($jsonBody) && $jsonBody !== [] ? $jsonBody : $_POST;

$trackId = cleanText($payload['id'] ?? '');
if ($trackId === '') {
    $trackId = cleanText($payload['slug'] ?? $payload['song'] ?? '');
}
$action = cleanText($payload['action'] ?? 'like', 20);
$clientId = cleanText($payload['client_id'] ?? '', 160);

if ($trackId === '') {
    respond(['ok' => false, 'error' => 'Song not found.'], 400);
    exit;
}

$shouldLike = $action !== 'unlike';
$clientHash = $clientId !== '' ? hash('sha256', $clientId) : '';

prepareTracksFile();
$handle = fopen(TRACKS_FILE, 'c+');

if ($handle === false) {
    respond(['ok' => false, 'error' => 'Likes are temporarily unavailable.'], 503);
    exit;
}

try {
    if (!flock($handle, LOCK_EX)) {
        respond(['ok' => false, 'error' => 'Likes are temporarily unavailable.'], 503);
        exit;
    }

    rewind($handle);
    $contents = stream_get_contents($handle) ?: '[]';
    $tracks = json_decode($contents, true);
    $tracks = is_array($tracks) ? $tracks : [];
    $matchedIndex = null;

    foreach ($tracks as $index => $track) {
        if (!is_array($track)) {
            continue;
        }

        $candidateId = (string) ($track['id'] ?? '');
        $candidateRawSlug = (string) ($track['slug'] ?? '');
        $candidateSlug = slugify($candidateRawSlug !== '' ? $candidateRawSlug : (string) ($track['title'] ?? $candidateId));

        if ($trackId === $candidateId || $trackId === $candidateRawSlug || $trackId === $candidateSlug) {
            $matchedIndex = $index;
            break;
        }
    }

    $track = $matchedIndex !== null && is_array($tracks[$matchedIndex]) ? $tracks[$matchedIndex] : [];
    $likeKeys = trackLikeKeys($track, $trackId);
    $canonicalKey = (string) ($track['id'] ?? '');
    $canonicalKey = $canonicalKey !== '' ? $canonicalKey : $likeKeys[0];
    $likesStore = readLikesStore();
    $storedEntry = findStoredLikeEntry($likesStore, $likeKeys) ?? [];
    $likes = max(0, (int) ($track['likes'] ?? $track['likeCount'] ?? 0));
    $storedLikes = max(0, (int) ($storedEntry['likes'] ?? $storedEntry['likeCount'] ?? 0));
    $likes = max($likes, $storedLikes);
    $trackClients = is_array($track['likedClients'] ?? null) ? $track['likedClients'] : [];
    $storedClients = is_array($storedEntry['likedClients'] ?? null) ? $storedEntry['likedClients'] : [];
    $likedClients = array_values(array_unique(array_merge($trackClients, $storedClients)));

    if ($clientHash !== '') {
        $alreadyLiked = in_array($clientHash, $likedClients, true);

        if ($shouldLike && !$alreadyLiked) {
            $likes++;
            $likedClients[] = $clientHash;
        } elseif (!$shouldLike && $alreadyLiked) {
            $likes = max(0, $likes - 1);
            $likedClients = array_values(array_filter(
                $likedClients,
                static fn ($hash): bool => $hash !== $clientHash
            ));
        }
    } else {
        $likes = $shouldLike ? $likes + 1 : max(0, $likes - 1);
    }

    $likedClients = array_slice(array_values(array_unique($likedClients)), -10000);
    $likeEvent = [
        'timestamp' => gmdate('c'),
        'action' => $shouldLike ? 'like' : 'unlike',
        'referrer' => cleanText($_SERVER['HTTP_REFERER'] ?? '', 600),
        'ip_hash' => hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? '')),
    ];

    if ($matchedIndex !== null && is_array($tracks[$matchedIndex])) {
        $track['likes'] = $likes;
        $track['likeCount'] = $likes;
        $track['likedClients'] = $likedClients;
        $track['lastLikedAt'] = gmdate('c');
        $track['likeEvents'] = is_array($track['likeEvents'] ?? null) ? $track['likeEvents'] : [];
        array_unshift($track['likeEvents'], $likeEvent);
        $track['likeEvents'] = array_slice($track['likeEvents'], 0, 100);
        $tracks[$matchedIndex] = $track;
    }

    $storeEvents = is_array($storedEntry['events'] ?? null) ? $storedEntry['events'] : [];
    array_unshift($storeEvents, $likeEvent);
    $likesStore[$canonicalKey] = [
        'likes' => $likes,
        'likeCount' => $likes,
        'likedClients' => $likedClients,
        'aliases' => $likeKeys,
        'updatedAt' => gmdate('c'),
        'events' => array_slice($storeEvents, 0, 100),
    ];

    rewind($handle);
    ftruncate($handle, 0);
    fwrite($handle, json_encode($tracks, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    fflush($handle);
    flock($handle, LOCK_UN);
    writeLikesStore($likesStore);

    respond([
        'ok' => true,
        'success' => true,
        'id' => (string) ($track['id'] ?? $trackId),
        'liked' => $shouldLike,
        'likes' => $likes,
    ]);
} finally {
    fclose($handle);
}
