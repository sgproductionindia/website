<?php
declare(strict_types=1);

const TRACKS_FILE = __DIR__ . '/../data/tracks.json';

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

function isHttpUrl(string $value): bool
{
    if (!filter_var($value, FILTER_VALIDATE_URL)) {
        return false;
    }

    $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));

    return in_array($scheme, ['http', 'https'], true);
}

function readTracks(): array
{
    $directory = dirname(TRACKS_FILE);

    if (!is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    if (!file_exists(TRACKS_FILE)) {
        file_put_contents(TRACKS_FILE, "[]\n", LOCK_EX);
    }

    $tracks = json_decode(file_get_contents(TRACKS_FILE) ?: '[]', true);

    return is_array($tracks) ? $tracks : [];
}

function redirectBack(string $message): void
{
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
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

$trackId = cleanText($_GET['id'] ?? $_GET['song'] ?? '');

if ($trackId === '') {
    redirectBack('Song not found.');
    exit;
}

prepareTracksFile();
$handle = fopen(TRACKS_FILE, 'c+');

if ($handle === false) {
    redirectBack('Downloads are temporarily unavailable.');
    exit;
}

$downloadUrl = '';

try {
    if (!flock($handle, LOCK_EX)) {
        redirectBack('Downloads are temporarily unavailable.');
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
        $candidateSlug = slugify((string) ($track['title'] ?? $candidateId));

        if ($trackId === $candidateId || $trackId === $candidateSlug) {
            $matchedIndex = $index;
            break;
        }
    }

    if ($matchedIndex === null || !is_array($tracks[$matchedIndex])) {
        redirectBack('Song not found.');
        exit;
    }

    $track = $tracks[$matchedIndex];
    $downloadUrl = trim((string) ($track['downloadUrl'] ?? ''));

    if ($downloadUrl === '' || !isHttpUrl($downloadUrl)) {
        redirectBack('Download link not available.');
        exit;
    }

    $track['downloads'] = (int) ($track['downloads'] ?? $track['downloadCount'] ?? 0) + 1;
    $track['downloadCount'] = $track['downloads'];
    $track['lastDownloadedAt'] = gmdate('c');
    $track['downloadEvents'] = is_array($track['downloadEvents'] ?? null) ? $track['downloadEvents'] : [];
    array_unshift($track['downloadEvents'], [
        'timestamp' => gmdate('c'),
        'referrer' => cleanText($_SERVER['HTTP_REFERER'] ?? '', 600),
        'ip_hash' => hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? '')),
    ]);
    $track['downloadEvents'] = array_slice($track['downloadEvents'], 0, 100);
    $tracks[$matchedIndex] = $track;

    rewind($handle);
    ftruncate($handle, 0);
    fwrite($handle, json_encode($tracks, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    fflush($handle);
    flock($handle, LOCK_UN);
} finally {
    fclose($handle);
}

header('Location: ' . $downloadUrl, true, 302);
exit;
