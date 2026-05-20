<?php
declare(strict_types=1);

session_start();

// Load central config (defines ROOT_DIR and UPLOADS_DIR and $pdo)
require_once rtrim($_SERVER['DOCUMENT_ROOT'] ?? __DIR__, '/') . '/config.php';

define('ADMIN_PASSWORD', (string) (getenv('ADMIN_PASSWORD') ?: 'change-me-before-deploy'));
define('TRACKS_FILE', (defined('ROOT_DIR') ? ROOT_DIR : __DIR__) . '/data/tracks.json');
define('SETTINGS_FILE', (defined('ROOT_DIR') ? ROOT_DIR : __DIR__) . '/data/settings.json');
define('ARTISTS_FILE', (defined('ROOT_DIR') ? ROOT_DIR : __DIR__) . '/data/artists.json');
define('GENRES_FILE', (defined('ROOT_DIR') ? ROOT_DIR : __DIR__) . '/data/genres.json');
define('AD_STATS_FILE', (defined('ROOT_DIR') ? ROOT_DIR : __DIR__) . '/data/ad-stats.json');
define('COVER_DIR', (defined('UPLOADS_DIR') ? UPLOADS_DIR : (__DIR__ . '/uploads')) . '/covers');
define('AUDIO_DIR', (defined('UPLOADS_DIR') ? UPLOADS_DIR : (__DIR__ . '/uploads')) . '/audio');
define('AD_DIR', (defined('UPLOADS_DIR') ? UPLOADS_DIR : (__DIR__ . '/uploads')) . '/ads');
define('ARTIST_DIR', (defined('UPLOADS_DIR') ? UPLOADS_DIR : (__DIR__ . '/uploads')) . '/artists');
define('SITE_MEDIA_DIR', (defined('UPLOADS_DIR') ? UPLOADS_DIR : (__DIR__ . '/uploads')) . '/site');
const MAX_COVER_BYTES = 8 * 1024 * 1024;
const MAX_AUDIO_BYTES = 120 * 1024 * 1024;
const MAX_AD_BYTES = 60 * 1024 * 1024;
const MAX_SITE_MEDIA_BYTES = 8 * 1024 * 1024;

$errors = [];
$success = '';

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function ensureStorage(): void
{
    foreach ([dirname(TRACKS_FILE), COVER_DIR, AUDIO_DIR, AD_DIR, ARTIST_DIR, SITE_MEDIA_DIR] as $directory) {
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if (!is_writable($directory)) {
            @chmod($directory, 0775);
        }
    }

    if (!file_exists(TRACKS_FILE)) {
        file_put_contents(TRACKS_FILE, "[]\n", LOCK_EX);
    }

    if (!file_exists(SETTINGS_FILE)) {
        file_put_contents(SETTINGS_FILE, json_encode(defaultSettings(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n", LOCK_EX);
    }

    if (!file_exists(ARTISTS_FILE)) {
        file_put_contents(ARTISTS_FILE, json_encode(defaultArtists(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n", LOCK_EX);
    }

    if (!file_exists(GENRES_FILE)) {
        file_put_contents(GENRES_FILE, json_encode(defaultGenres(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n", LOCK_EX);
    }

    if (!file_exists(AD_STATS_FILE)) {
        file_put_contents(AD_STATS_FILE, json_encode(defaultAdStats(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n", LOCK_EX);
    }
}

function uploadErrorMessage(int $code): string
{
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The uploaded file is larger than the server upload limit.',
        UPLOAD_ERR_PARTIAL => 'The file upload was interrupted before it finished.',
        UPLOAD_ERR_NO_FILE => 'Please choose a file to upload.',
        UPLOAD_ERR_NO_TMP_DIR => 'The server is missing a temporary upload folder.',
        UPLOAD_ERR_CANT_WRITE => 'The server could not write the uploaded file to disk.',
        UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.',
        default => 'Upload failed. Please try again.',
    };
}

function slugify(string $value): string
{
    $slug = strtolower(trim($value));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    $slug = trim($slug, '-');

    return $slug !== '' ? $slug : 'track';
}

function uniqueTrackId(string $title, array $tracks): string
{
    $baseId = slugify($title);
    $existingIds = array_flip(array_map(static fn ($track): string => is_array($track) ? (string) ($track['id'] ?? '') : '', $tracks));

    if (!isset($existingIds[$baseId])) {
        return $baseId;
    }

    $counter = 2;

    while (isset($existingIds[$baseId . '-' . $counter])) {
        $counter += 1;
    }

    return $baseId . '-' . $counter;
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
    ensureStorage();
    $json = file_get_contents(TRACKS_FILE);
    $tracks = json_decode($json ?: '[]', true);

    return is_array($tracks) ? $tracks : [];
}

function defaultSettings(): array
{
    return [
        'site' => [
            'title' => 'SG Production',
            'tagline' => 'Original music • direct download • no barriers',
            'youtubeHeading' => 'Subscribe on YouTube',
            'youtubeText' => 'Watch latest music releases, behind-the-scenes clips, and official SG Production updates on the YouTube channel.',
            'contactEmail' => 'bookings@sgproduction.example',
        ],
        'links' => [
            'instagram' => 'https://www.instagram.com/sgproduction.music',
            'spotify' => 'https://open.spotify.com/artist/2FeM1GdzeY1ZnT8rJLYKHb?autoplay=true',
            'appleMusic' => 'https://music.apple.com/in/artist/sg-production/1580814477',
            'youtube' => 'https://www.youtube.com/@sgproductionindia',
        ],
        'seo' => [
            'metaDescription' => 'SG Production is an independent artist music catalog with direct downloads, latest releases, and original tracks.',
            'ogImage' => 'assets/cover-1.jpg',
            'favicon' => 'assets/sg-logo.svg',
        ],
        'catalog' => [
            'latestCount' => 5,
            'tracksPerPage' => 15,
            'paginationDemoPages' => 12,
        ],
        'advertising' => [
            'enabled' => false,
            'mediaUrl' => '',
            'mediaType' => '',
            'linkUrl' => '',
            'gridAd' => [
                'enabled' => false,
                'imageUrl' => '',
                'name' => '',
                'subtext' => '',
                'buttonText' => 'Learn more',
                'buttonColor' => '#ffffff',
                'buttonTextColor' => '#000000',
                'linkUrl' => '',
                'position' => 8,
            ],
        ],
    ];
}

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

function defaultGenres(): array
{
    return [
        [
            'id' => 'original-mix',
            'name' => 'Original Mix',
            'slug' => 'original-mix',
            'description' => 'Original SG Production mixes and main catalog releases.',
            'color' => '#0a84ff',
        ],
        [
            'id' => 'soundcheck',
            'name' => 'Soundcheck',
            'slug' => 'soundcheck',
            'description' => 'Soundcheck focused drops and test mixes.',
            'color' => '#00e6a8',
        ],
        [
            'id' => 'marathi',
            'name' => 'Marathi',
            'slug' => 'marathi',
            'description' => 'Marathi inspired releases.',
            'color' => '#8b5cf6',
        ],
        [
            'id' => 'hindi',
            'name' => 'Hindi',
            'slug' => 'hindi',
            'description' => 'Hindi catalog releases.',
            'color' => '#f6b73c',
        ],
    ];
}

function defaultArtists(): array
{
    return [
        [
            'id' => 'sg-production',
            'name' => 'SG Production',
            'style' => 'Original Mix',
            'image' => 'assets/artist-photo-1.svg',
            'year' => '2026',
            'trackGenres' => ['Original Mix', 'Marathi', 'Soundcheck', 'Hindi'],
        ],
        [
            'id' => 'sg-soundcheck',
            'name' => 'SG Soundcheck',
            'style' => 'Soundcheck',
            'image' => 'assets/artist-photo-2.svg',
            'year' => '2026',
            'trackGenres' => ['Soundcheck'],
        ],
        [
            'id' => 'marathi-pulse',
            'name' => 'Marathi Pulse',
            'style' => 'Marathi',
            'image' => 'assets/artist-photo-3.svg',
            'year' => '2025',
            'trackGenres' => ['Marathi'],
        ],
        [
            'id' => 'hindi-wave',
            'name' => 'Hindi Wave',
            'style' => 'Hindi',
            'image' => 'assets/artist-photo-4.svg',
            'year' => '2025',
            'trackGenres' => ['Hindi'],
        ],
        [
            'id' => 'night-circuit',
            'name' => 'Night Circuit',
            'style' => 'Original Mix',
            'image' => 'assets/artist-photo-5.svg',
            'year' => '2024',
            'trackGenres' => ['Original Mix'],
        ],
    ];
}

function readSettings(): array
{
    ensureStorage();
    $json = file_get_contents(SETTINGS_FILE);
    $settings = json_decode($json ?: '{}', true);

    if (!is_array($settings)) {
        return defaultSettings();
    }

    return array_replace_recursive(defaultSettings(), $settings);
}

function readArtists(): array
{
    ensureStorage();
    $json = file_get_contents(ARTISTS_FILE);
    $artists = json_decode($json ?: '[]', true);

    return is_array($artists) ? $artists : defaultArtists();
}

function readGenres(): array
{
    ensureStorage();
    $json = file_get_contents(GENRES_FILE);
    $genres = json_decode($json ?: '[]', true);

    return is_array($genres) ? $genres : defaultGenres();
}

function writeGenres(array $genres): void
{
    $json = json_encode(array_values($genres), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    if ($json === false || file_put_contents(GENRES_FILE, $json . "\n", LOCK_EX) === false) {
        throw new RuntimeException('Could not save genres.');
    }
}

function genreUsageCounts(string $genreName, array $tracks, array $artists): array
{
    $needle = strtolower(trim($genreName));
    $songCount = 0;
    $artistCount = 0;

    foreach ($tracks as $track) {
        if (is_array($track) && strtolower(trim((string) ($track['genre'] ?? ''))) === $needle) {
            $songCount += 1;
        }
    }

    foreach ($artists as $artist) {
        $artistGenres = is_array($artist['trackGenres'] ?? null) ? $artist['trackGenres'] : [];
        foreach ($artistGenres as $artistGenre) {
            if (strtolower(trim((string) $artistGenre)) === $needle) {
                $artistCount += 1;
                break;
            }
        }
    }

    return ['songs' => $songCount, 'artists' => $artistCount];
}

function readAdStats(): array
{
    ensureStorage();
    $json = file_get_contents(AD_STATS_FILE);
    $stats = json_decode($json ?: '{}', true);

    return is_array($stats) ? array_replace_recursive(defaultAdStats(), $stats) : defaultAdStats();
}

function writeSettings(array $settings): void
{
    $json = json_encode(array_replace_recursive(defaultSettings(), $settings), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    if ($json === false || file_put_contents(SETTINGS_FILE, $json . "\n", LOCK_EX) === false) {
        throw new RuntimeException('Could not save website settings.');
    }
}

function writeTracks(array $tracks): void
{
    $json = json_encode($tracks, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    if ($json === false || file_put_contents(TRACKS_FILE, $json . "\n", LOCK_EX) === false) {
        throw new RuntimeException('Could not save the track list.');
    }
}

function writeArtists(array $artists): void
{
    $json = json_encode(array_values($artists), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    if ($json === false || file_put_contents(ARTISTS_FILE, $json . "\n", LOCK_EX) === false) {
        throw new RuntimeException('Could not save artist profiles.');
    }
}

function findIndexById(array $items, string $id): ?int
{
    foreach ($items as $index => $item) {
        if (is_array($item) && (string) ($item['id'] ?? '') === $id) {
            return (int) $index;
        }
    }

    return null;
}

function parseList(string $value): array
{
    return array_values(array_filter(array_map('trim', explode(',', $value)), static fn (string $item): bool => $item !== ''));
}

function safeDeleteUpload(?string $path): void
{
    $path = (string) $path;

    if ($path === '' || str_starts_with($path, 'assets/')) {
        return;
    }

    $fullPath = realpath(__DIR__ . '/' . $path);
    $root = realpath(__DIR__ . '/uploads');

    if ($fullPath !== false && $root !== false && str_starts_with($fullPath, $root) && is_file($fullPath)) {
        @unlink($fullPath);
    }
}

function folderSize(string $directory): int
{
    if (!is_dir($directory)) {
        return 0;
    }

    $size = 0;
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));

    foreach ($files as $file) {
        if ($file->isFile()) {
            $size += $file->getSize();
        }
    }

    return $size;
}

function formatBytes(int $bytes): string
{
    if ($bytes >= 1024 * 1024) {
        return round($bytes / 1024 / 1024, 1) . ' MB';
    }

    if ($bytes >= 1024) {
        return round($bytes / 1024, 1) . ' KB';
    }

    return $bytes . ' B';
}

function uniqueTargetPath(string $directory, string $baseName, string $extension): string
{
    $safeBase = slugify($baseName);
    $candidate = $directory . '/' . $safeBase . '.' . $extension;
    $counter = 2;

    while (file_exists($candidate)) {
        $candidate = $directory . '/' . $safeBase . '-' . $counter . '.' . $extension;
        $counter += 1;
    }

    return $candidate;
}

function uploadFile(string $field, array $extensions, array $mimeTypes, int $maxBytes, string $directory, string $baseName): string
{
    if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) {
        throw new RuntimeException('Missing upload file.');
    }

    $file = $_FILES[$field];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException(uploadErrorMessage((int) $file['error']));
    }

    if ($file['size'] <= 0 || $file['size'] > $maxBytes) {
        throw new RuntimeException('File is too large for this upload. Maximum allowed here is ' . (int) floor($maxBytes / 1024 / 1024) . ' MB.');
    }

    $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));

    if (!in_array($extension, $extensions, true)) {
        throw new RuntimeException('This file type is not allowed.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file((string) $file['tmp_name']);

    $genericUploadMimes = ['application/octet-stream', 'binary/octet-stream'];

    if ($mime !== false && !in_array($mime, $mimeTypes, true) && !in_array($mime, $genericUploadMimes, true)) {
        throw new RuntimeException('The uploaded file does not match the expected format.');
    }

    $target = uniqueTargetPath($directory, $baseName, $extension);

    if (!is_writable($directory)) {
        throw new RuntimeException('Upload folder is not writable: ' . basename($directory) . '. Check the Coolify volume permissions.');
    }

    if (!move_uploaded_file((string) $file['tmp_name'], $target)) {
        throw new RuntimeException('Could not save the uploaded file. Check that the Coolify volume for uploads is writable by PHP.');
    }

    $baseRoot = defined('ROOT_DIR') ? ROOT_DIR : __DIR__;
    $webPath = '/' . ltrim(str_replace($baseRoot, '', $target), '/');
    return $webPath;
}

function uploadOptionalFile(string $field, array $extensions, array $mimeTypes, int $maxBytes, string $directory, string $baseName): ?string
{
    if (!isset($_FILES[$field]) || !is_array($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    return uploadFile($field, $extensions, $mimeTypes, $maxBytes, $directory, $baseName);
}

function formatDurationSeconds(float $seconds): string
{
    $totalSeconds = max(0, (int) round($seconds));

    return intdiv($totalSeconds, 60) . ':' . ($totalSeconds % 60);
}

function unpackUInt32LE(string $bytes): int
{
    $value = unpack('V', $bytes);

    return is_array($value) ? (int) $value[1] : 0;
}

function detectWavDuration(string $path): ?float
{
    $handle = fopen($path, 'rb');

    if ($handle === false) {
        return null;
    }

    try {
        $header = fread($handle, 12);

        if ($header === false || strlen($header) < 12 || substr($header, 0, 4) !== 'RIFF' || substr($header, 8, 4) !== 'WAVE') {
            return null;
        }

        $byteRate = 0;

        while (!feof($handle)) {
            $chunkId = fread($handle, 4);
            $chunkSizeBytes = fread($handle, 4);

            if ($chunkId === false || $chunkSizeBytes === false || strlen($chunkId) < 4 || strlen($chunkSizeBytes) < 4) {
                break;
            }

            $chunkSize = unpackUInt32LE($chunkSizeBytes);

            if ($chunkId === 'fmt ') {
                $format = fread($handle, min($chunkSize, 16));

                if ($format !== false && strlen($format) >= 12) {
                    $byteRate = unpackUInt32LE(substr($format, 8, 4));
                }

                $remaining = $chunkSize - strlen((string) $format);
                if ($remaining > 0) {
                    fseek($handle, $remaining, SEEK_CUR);
                }
            } elseif ($chunkId === 'data' && $byteRate > 0) {
                return $chunkSize / $byteRate;
            } else {
                fseek($handle, $chunkSize, SEEK_CUR);
            }

            if ($chunkSize % 2 === 1) {
                fseek($handle, 1, SEEK_CUR);
            }
        }
    } finally {
        fclose($handle);
    }

    return null;
}

function detectMp3Duration(string $path): ?float
{
    $data = file_get_contents($path, false, null, 0, 262144);

    if ($data === false || strlen($data) < 10) {
        return null;
    }

    $offset = 0;

    if (substr($data, 0, 3) === 'ID3' && strlen($data) >= 10) {
        $offset = ((ord($data[6]) & 0x7f) << 21)
            | ((ord($data[7]) & 0x7f) << 14)
            | ((ord($data[8]) & 0x7f) << 7)
            | (ord($data[9]) & 0x7f);
        $offset += 10;
    }

    $bitrates = [
        1 => [1 => 32, 2 => 40, 3 => 48, 4 => 56, 5 => 64, 6 => 80, 7 => 96, 8 => 112, 9 => 128, 10 => 160, 11 => 192, 12 => 224, 13 => 256, 14 => 320],
        2 => [1 => 8, 2 => 16, 3 => 24, 4 => 32, 5 => 40, 6 => 48, 7 => 56, 8 => 64, 9 => 80, 10 => 96, 11 => 112, 12 => 128, 13 => 144, 14 => 160],
    ];

    for ($index = $offset; $index < strlen($data) - 4; $index += 1) {
        $b1 = ord($data[$index]);
        $b2 = ord($data[$index + 1]);

        if ($b1 !== 0xff || ($b2 & 0xe0) !== 0xe0) {
            continue;
        }

        $b3 = ord($data[$index + 2]);
        $versionBits = ($b2 >> 3) & 0x03;
        $layerBits = ($b2 >> 1) & 0x03;
        $bitrateIndex = ($b3 >> 4) & 0x0f;

        if ($versionBits === 1 || $layerBits !== 1 || $bitrateIndex === 0 || $bitrateIndex === 15) {
            continue;
        }

        $versionKey = $versionBits === 3 ? 1 : 2;
        $bitrateKbps = $bitrates[$versionKey][$bitrateIndex] ?? 0;

        if ($bitrateKbps <= 0) {
            return null;
        }

        $fileSize = filesize($path);

        if ($fileSize === false || $fileSize <= $offset) {
            return null;
        }

        $audioBytes = $fileSize - $offset;

        return $audioBytes / (($bitrateKbps * 1000) / 8);
    }

    return null;
}

function detectAudioDuration(string $path): ?float
{
    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

    if ($extension === 'wav') {
        return detectWavDuration($path);
    }

    if ($extension === 'mp3') {
        return detectMp3Duration($path);
    }

    return null;
}

ensureStorage();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'logout') {
    $_SESSION = [];
    session_destroy();
    header('Location: admin.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    if (hash_equals(ADMIN_PASSWORD, (string) ($_POST['password'] ?? ''))) {
        $_SESSION['sg_admin'] = true;
        header('Location: admin.php');
        exit;
    }

    $errors[] = 'Wrong password.';
}

$isAuthed = !empty($_SESSION['sg_admin']);

if (isset($_SESSION['flash_success'])) {
    $success = (string) $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

if ($isAuthed && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload') {
    try {
        $title = trim((string) ($_POST['title'] ?? ''));
        $artist = trim((string) ($_POST['artist'] ?? 'SG Production'));
        $artistId = trim((string) ($_POST['artistId'] ?? 'sg-production'));
        $genre = trim((string) ($_POST['genre'] ?? 'Soundcheck'));
        $duration = trim((string) ($_POST['duration'] ?? ''));
        $downloadUrl = trim((string) ($_POST['downloadUrl'] ?? ''));
        $bpm = (int) ($_POST['bpm'] ?? 124);
        $wave = trim((string) ($_POST['wave'] ?? 'sine'));
        $creditText = trim((string) ($_POST['creditText'] ?? ''));

        if ($title === '') {
            throw new RuntimeException('Track title is required.');
        }

        if ($downloadUrl === '' || !isHttpUrl($downloadUrl)) {
            throw new RuntimeException('Add a valid WAV download URL that starts with http:// or https://.');
        }

        if ($bpm < 40 || $bpm > 240) {
            throw new RuntimeException('BPM must be between 40 and 240.');
        }

        $tracks = readTracks();

        foreach ($tracks as $track) {
            if (!is_array($track)) {
                continue;
            }

            $sameTitle = slugify((string) ($track['title'] ?? '')) === slugify($title);
            $sameDownload = (string) ($track['downloadUrl'] ?? '') === $downloadUrl;

            if ($sameTitle && $sameDownload) {
                throw new RuntimeException('This song is already published.');
            }
        }

        $trackId = uniqueTrackId($title, $tracks);
        $cover = uploadFile('cover', ['jpg', 'jpeg', 'png', 'webp'], ['image/jpeg', 'image/png', 'image/webp'], MAX_COVER_BYTES, COVER_DIR, $trackId);
        $previewAudio = uploadFile('audio', ['wav', 'mp3'], ['audio/wav', 'audio/wave', 'audio/x-wav', 'audio/x-pn-wav', 'audio/mpeg', 'audio/mp3', 'audio/x-mpeg'], MAX_AUDIO_BYTES, AUDIO_DIR, $trackId);
        $detectedDuration = detectAudioDuration(__DIR__ . '/' . $previewAudio);

        if ($detectedDuration !== null) {
            $duration = formatDurationSeconds($detectedDuration);
        }

        if (!preg_match('/^\d+:\d{1,2}$/', $duration)) {
            throw new RuntimeException('Could not detect the song duration. Please try a different WAV or MP3 file.');
        }

        array_unshift($tracks, [
            'id' => $trackId,
            'title' => $title,
            'artist' => $artist !== '' ? $artist : 'SG Production',
            'artistId' => $artistId !== '' ? $artistId : 'sg-production',
            'genre' => $genre !== '' ? $genre : 'Soundcheck',
            'duration' => $duration,
            'cover' => $cover,
            'previewUrl' => $previewAudio,
            'downloadUrl' => $downloadUrl,
            'creditText' => $creditText,
            'downloads' => 0,
            'downloadCount' => 0,
            'isNew' => isset($_POST['isNew']),
            'isFeatured' => isset($_POST['isFeatured']) || isset($_POST['isNew']),
            'bpm' => $bpm,
            'tone' => 146.83,
            'wave' => in_array($wave, ['sine', 'triangle', 'sawtooth', 'square'], true) ? $wave : 'sine',
            'createdAt' => date('c'),
        ]);

        writeTracks($tracks);
        $_SESSION['flash_success'] = 'Song uploaded successfully.';
        header('Location: admin.php#songs');
        exit;
    } catch (Throwable $error) {
        $errors[] = $error->getMessage();
    }
}

$settings = readSettings();

if ($isAuthed && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_ad') {
    try {
        $advertising = is_array($settings['advertising'] ?? null) ? $settings['advertising'] : [];
        $uploadedAd = uploadOptionalFile(
            'adMedia',
            ['jpg', 'jpeg', 'png', 'webp', 'mp4', 'webm', 'mov'],
            ['image/jpeg', 'image/png', 'image/webp', 'video/mp4', 'video/webm', 'video/quicktime'],
            MAX_AD_BYTES,
            AD_DIR,
            'advertisement-' . date('YmdHis')
        );

        if ($uploadedAd !== null) {
            $extension = strtolower(pathinfo($uploadedAd, PATHINFO_EXTENSION));
            $advertising['mediaUrl'] = $uploadedAd;
            $advertising['mediaType'] = in_array($extension, ['mp4', 'webm', 'mov'], true) ? 'video' : 'image';
        }

        $advertising['enabled'] = isset($_POST['adEnabled']);
        $advertising['linkUrl'] = trim((string) ($_POST['adLinkUrl'] ?? ($advertising['linkUrl'] ?? '')));

        if ($advertising['enabled'] && trim((string) ($advertising['mediaUrl'] ?? '')) === '') {
            throw new RuntimeException('Upload an advertising image or video before turning advertising on.');
        }

        // Grid ad
        $gridAd = is_array($advertising['gridAd'] ?? null) ? $advertising['gridAd'] : [];
        $uploadedGridAdImage = uploadOptionalFile(
            'gridAdImage',
            ['jpg', 'jpeg', 'png', 'webp'],
            ['image/jpeg', 'image/png', 'image/webp'],
            MAX_AD_BYTES,
            AD_DIR,
            'grid-ad-' . date('YmdHis')
        );
        if ($uploadedGridAdImage !== null) {
            $gridAd['imageUrl'] = $uploadedGridAdImage;
        }
        $gridAd['enabled']         = isset($_POST['gridAdEnabled']);
        $gridAd['name']            = trim((string) ($_POST['gridAdName'] ?? ($gridAd['name'] ?? '')));
        $gridAd['subtext']         = trim((string) ($_POST['gridAdSubtext'] ?? ($gridAd['subtext'] ?? '')));
        $gridAd['buttonText']      = trim((string) ($_POST['gridAdButtonText'] ?? ($gridAd['buttonText'] ?? 'Learn more')));
        $gridAd['buttonColor']     = trim((string) ($_POST['gridAdButtonColor'] ?? ($gridAd['buttonColor'] ?? '#ffffff')));
        $gridAd['buttonTextColor'] = trim((string) ($_POST['gridAdButtonTextColor'] ?? ($gridAd['buttonTextColor'] ?? '#000000')));
        $gridAd['linkUrl']         = trim((string) ($_POST['gridAdLinkUrl'] ?? ($gridAd['linkUrl'] ?? '')));
        $gridAd['position']        = max(1, min(50, (int) ($_POST['gridAdPosition'] ?? ($gridAd['position'] ?? 8))));
        $advertising['gridAd']     = $gridAd;

        $settings['advertising'] = $advertising;
        writeSettings($settings);
        $_SESSION['flash_success'] = 'Advertising settings saved.';
        header('Location: admin.php#advertising');
        exit;
    } catch (Throwable $error) {
        $errors[] = $error->getMessage();
    }
}

if ($isAuthed && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_site') {
    try {
        $settings = readSettings();
        $latestCount = max(0, min(12, (int) ($_POST['latestCount'] ?? 5)));
        $tracksPerPage = max(5, min(50, (int) ($_POST['tracksPerPage'] ?? 15)));
        $paginationDemoPages = max(1, min(40, (int) ($_POST['paginationDemoPages'] ?? 12)));
        $seo = is_array($settings['seo'] ?? null) ? $settings['seo'] : defaultSettings()['seo'];
        $uploadedOgImage = uploadOptionalFile(
            'ogImage',
            ['jpg', 'jpeg', 'png', 'webp'],
            ['image/jpeg', 'image/png', 'image/webp'],
            MAX_SITE_MEDIA_BYTES,
            SITE_MEDIA_DIR,
            'og-image-' . date('YmdHis')
        );
        $uploadedFavicon = uploadOptionalFile(
            'favicon',
            ['ico', 'png', 'jpg', 'jpeg', 'webp', 'svg'],
            ['image/vnd.microsoft.icon', 'image/x-icon', 'image/jpeg', 'image/png', 'image/webp', 'image/svg+xml', 'text/plain'],
            MAX_SITE_MEDIA_BYTES,
            SITE_MEDIA_DIR,
            'favicon-' . date('YmdHis')
        );

        $settings['site'] = [
            'title' => trim((string) ($_POST['siteTitle'] ?? 'SG Production')) ?: 'SG Production',
            'tagline' => trim((string) ($_POST['tagline'] ?? 'Original music • direct download • no barriers')) ?: 'Original music • direct download • no barriers',
            'youtubeHeading' => trim((string) ($_POST['youtubeHeading'] ?? 'Subscribe on YouTube')) ?: 'Subscribe on YouTube',
            'youtubeText' => trim((string) ($_POST['youtubeText'] ?? '')) ?: defaultSettings()['site']['youtubeText'],
            'contactEmail' => trim((string) ($_POST['contactEmail'] ?? 'bookings@sgproduction.example')) ?: 'bookings@sgproduction.example',
        ];
        $settings['links'] = [
            'instagram' => trim((string) ($_POST['instagram'] ?? '')),
            'spotify' => trim((string) ($_POST['spotify'] ?? '')),
            'appleMusic' => trim((string) ($_POST['appleMusic'] ?? '')),
            'youtube' => trim((string) ($_POST['youtube'] ?? '')),
        ];
        $settings['seo'] = [
            'metaDescription' => substr(trim((string) ($_POST['metaDescription'] ?? ($seo['metaDescription'] ?? defaultSettings()['seo']['metaDescription']))), 0, 160),
            'ogImage' => $uploadedOgImage ?? (string) ($seo['ogImage'] ?? defaultSettings()['seo']['ogImage']),
            'favicon' => $uploadedFavicon ?? (string) ($seo['favicon'] ?? defaultSettings()['seo']['favicon']),
        ];
        $settings['catalog'] = [
            'latestCount' => $latestCount,
            'tracksPerPage' => $tracksPerPage,
            'paginationDemoPages' => $paginationDemoPages,
        ];

        writeSettings($settings);
        $_SESSION['flash_success'] = 'Website settings saved.';
        header('Location: admin.php#settings');
        exit;
    } catch (Throwable $error) {
        $errors[] = $error->getMessage();
    }
}

if ($isAuthed && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_track') {
    try {
        $trackId = (string) ($_POST['trackId'] ?? '');
        $tracks = readTracks();
        $index = findIndexById($tracks, $trackId);

        if ($index === null) {
            throw new RuntimeException('Song not found.');
        }

        $track = is_array($tracks[$index]) ? $tracks[$index] : [];
        $title = trim((string) ($_POST['title'] ?? ''));
        $downloadUrl = trim((string) ($_POST['downloadUrl'] ?? ''));

        if ($title === '') {
            throw new RuntimeException('Track title is required.');
        }

        if ($downloadUrl === '' || !isHttpUrl($downloadUrl)) {
            throw new RuntimeException('Add a valid WAV download URL that starts with http:// or https://.');
        }

        $bpm = max(40, min(240, (int) ($_POST['bpm'] ?? 124)));
        $cover = uploadOptionalFile('cover', ['jpg', 'jpeg', 'png', 'webp'], ['image/jpeg', 'image/png', 'image/webp'], MAX_COVER_BYTES, COVER_DIR, $trackId);
        $previewAudio = uploadOptionalFile('audio', ['wav', 'mp3'], ['audio/wav', 'audio/wave', 'audio/x-wav', 'audio/x-pn-wav', 'audio/mpeg', 'audio/mp3', 'audio/x-mpeg'], MAX_AUDIO_BYTES, AUDIO_DIR, $trackId);
        $duration = trim((string) ($_POST['duration'] ?? ($track['duration'] ?? '0:0')));

        if ($previewAudio !== null) {
            $detectedDuration = detectAudioDuration(__DIR__ . '/' . $previewAudio);

            if ($detectedDuration !== null) {
                $duration = formatDurationSeconds($detectedDuration);
            }
        }

        if (!preg_match('/^\d+:\d{1,2}$/', $duration)) {
            throw new RuntimeException('Duration must use M:S format.');
        }

        if ($cover !== null) {
            safeDeleteUpload((string) ($track['cover'] ?? ''));
            $track['cover'] = $cover;
        }

        if ($previewAudio !== null) {
            safeDeleteUpload((string) ($track['previewUrl'] ?? ''));
            $track['previewUrl'] = $previewAudio;
        }

        $wave = trim((string) ($_POST['wave'] ?? 'sine'));
        $track['title'] = $title;
        $track['artist'] = trim((string) ($_POST['artist'] ?? 'SG Production')) ?: 'SG Production';
        $track['artistId'] = trim((string) ($_POST['artistId'] ?? 'sg-production')) ?: 'sg-production';
        $track['genre'] = trim((string) ($_POST['genre'] ?? 'Soundcheck')) ?: 'Soundcheck';
        $track['duration'] = $duration;
        $track['downloadUrl'] = $downloadUrl;
        $track['creditText'] = trim((string) ($_POST['creditText'] ?? ($track['creditText'] ?? '')));
        $track['isNew'] = isset($_POST['isNew']);
        $track['isFeatured'] = isset($_POST['isFeatured']);
        $track['bpm'] = $bpm;
        $track['wave'] = in_array($wave, ['sine', 'triangle', 'sawtooth', 'square'], true) ? $wave : 'sine';

        $tracks[$index] = $track;
        writeTracks($tracks);
        $_SESSION['flash_success'] = 'Song updated.';
        header('Location: admin.php#songs');
        exit;
    } catch (Throwable $error) {
        $errors[] = $error->getMessage();
    }
}

if ($isAuthed && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_track') {
    try {
        $trackId = (string) ($_POST['trackId'] ?? '');
        $tracks = readTracks();
        $index = findIndexById($tracks, $trackId);

        if ($index === null) {
            throw new RuntimeException('Song not found.');
        }

        $track = is_array($tracks[$index]) ? $tracks[$index] : [];

        if (isset($_POST['deleteFiles'])) {
            safeDeleteUpload((string) ($track['cover'] ?? ''));
            safeDeleteUpload((string) ($track['previewUrl'] ?? ''));
        }

        array_splice($tracks, $index, 1);
        writeTracks($tracks);
        $_SESSION['flash_success'] = 'Song deleted.';
        header('Location: admin.php#songs');
        exit;
    } catch (Throwable $error) {
        $errors[] = $error->getMessage();
    }
}

if ($isAuthed && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'move_track') {
    try {
        $trackId = (string) ($_POST['trackId'] ?? '');
        $direction = (string) ($_POST['direction'] ?? '');
        $tracks = readTracks();
        $index = findIndexById($tracks, $trackId);

        if ($index === null) {
            throw new RuntimeException('Song not found.');
        }

        $target = $direction === 'up' ? $index - 1 : $index + 1;

        if (isset($tracks[$target])) {
            [$tracks[$index], $tracks[$target]] = [$tracks[$target], $tracks[$index]];
            writeTracks($tracks);
        }

        $_SESSION['flash_success'] = 'Song order updated.';
        header('Location: admin.php#songs');
        exit;
    } catch (Throwable $error) {
        $errors[] = $error->getMessage();
    }
}

if ($isAuthed && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_artist') {
    try {
        $artists = readArtists();
        $artistId = trim((string) ($_POST['artistId'] ?? ''));
        $name = trim((string) ($_POST['artistName'] ?? ''));

        if ($name === '') {
            throw new RuntimeException('Artist name is required.');
        }

        $isNewArtist = $artistId === '';
        $artistId = $isNewArtist ? uniqueTrackId($name, $artists) : $artistId;
        $index = findIndexById($artists, $artistId);
        $artist = $index === null ? ['id' => $artistId] : (is_array($artists[$index]) ? $artists[$index] : ['id' => $artistId]);
        $artistImage = uploadOptionalFile('artistImage', ['jpg', 'jpeg', 'png', 'webp', 'svg'], ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml', 'text/plain'], MAX_COVER_BYTES, ARTIST_DIR, $artistId);

        if ($artistImage !== null) {
            safeDeleteUpload((string) ($artist['image'] ?? ''));
            $artist['image'] = $artistImage;
        }

        $artist['name'] = $name;

        if (empty($artist['image'])) {
            $artist['image'] = 'assets/artist-photo-1.svg';
        }

        if ($index === null) {
            array_unshift($artists, $artist);
        } else {
            $artists[$index] = $artist;
        }

        writeArtists($artists);
        $_SESSION['flash_success'] = 'Artist profile saved.';
        header('Location: admin.php#artists');
        exit;
    } catch (Throwable $error) {
        $errors[] = $error->getMessage();
    }
}

if ($isAuthed && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_artist') {
    try {
        $artistId = (string) ($_POST['artistId'] ?? '');
        $artists = readArtists();
        $index = findIndexById($artists, $artistId);

        if ($index === null) {
            throw new RuntimeException('Artist not found.');
        }

        safeDeleteUpload((string) ($artists[$index]['image'] ?? ''));
        array_splice($artists, $index, 1);
        writeArtists($artists);
        $_SESSION['flash_success'] = 'Artist deleted.';
        header('Location: admin.php#artists');
        exit;
    } catch (Throwable $error) {
        $errors[] = $error->getMessage();
    }
}

if ($isAuthed && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_genre') {
    try {
        $genres = readGenres();
        $genreId = trim((string) ($_POST['genreId'] ?? ''));
        $name = trim((string) ($_POST['genreName'] ?? ''));
        $slug = slugify((string) ($_POST['genreSlug'] ?? $name));
        $description = substr(trim((string) ($_POST['genreDescription'] ?? '')), 0, 150);
        $color = trim((string) ($_POST['genreColor'] ?? '#0a84ff'));

        if ($name === '') {
            throw new RuntimeException('Genre name is required.');
        }

        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            $color = '#0a84ff';
        }

        $isNewGenre = $genreId === '';
        $genreId = $isNewGenre ? $slug : $genreId;
        $index = findIndexById($genres, $genreId);

        if ($isNewGenre && $index !== null) {
            throw new RuntimeException('This genre already exists.');
        }

        $genre = [
            'id' => $genreId,
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'color' => $color,
        ];

        if ($index === null) {
            array_unshift($genres, $genre);
        } else {
            $genres[$index] = $genre;
        }

        writeGenres($genres);
        $_SESSION['flash_success'] = 'Genre saved.';
        header('Location: admin.php#genres');
        exit;
    } catch (Throwable $error) {
        $errors[] = $error->getMessage();
    }
}

if ($isAuthed && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_genre') {
    try {
        $genreId = (string) ($_POST['genreId'] ?? '');
        $genres = readGenres();
        $tracksForGenre = readTracks();
        $artistsForGenre = readArtists();
        $index = findIndexById($genres, $genreId);

        if ($index === null) {
            throw new RuntimeException('Genre not found.');
        }

        $genreName = (string) ($genres[$index]['name'] ?? '');
        $counts = genreUsageCounts($genreName, $tracksForGenre, $artistsForGenre);

        if ($counts['songs'] > 0) {
            throw new RuntimeException('Cannot delete. Reassign or remove ' . $counts['songs'] . ' songs first.');
        }

        foreach ($artistsForGenre as $artistIndex => $artist) {
            if (!is_array($artist)) {
                continue;
            }

            $artistGenres = is_array($artist['trackGenres'] ?? null) ? $artist['trackGenres'] : [];
            $artistsForGenre[$artistIndex]['trackGenres'] = array_values(array_filter($artistGenres, static fn ($artistGenre): bool => strtolower(trim((string) $artistGenre)) !== strtolower(trim($genreName))));
        }

        array_splice($genres, $index, 1);
        writeGenres($genres);
        writeArtists($artistsForGenre);
        $_SESSION['flash_success'] = 'Genre deleted.';
        header('Location: admin.php#genres');
        exit;
    } catch (Throwable $error) {
        $errors[] = $error->getMessage();
    }
}

$settings = readSettings();
$tracks = readTracks();
$artists = readArtists();
$genres = readGenres();

?>
<?php
$site = is_array($settings['site'] ?? null) ? $settings['site'] : [];
$links = is_array($settings['links'] ?? null) ? $settings['links'] : [];
$seo = is_array($settings['seo'] ?? null) ? $settings['seo'] : defaultSettings()['seo'];
$catalog = is_array($settings['catalog'] ?? null) ? $settings['catalog'] : [];
$advertising = is_array($settings['advertising'] ?? null) ? $settings['advertising'] : defaultSettings()['advertising'];
$adStats = readAdStats();
$adTotals = is_array($adStats['totals'] ?? null) ? $adStats['totals'] : [];
$adSongs = is_array($adStats['songs'] ?? null) ? $adStats['songs'] : [];
$adEvents = is_array($adStats['events'] ?? null) ? $adStats['events'] : [];
$adImpressions = (int) ($adTotals['impressions'] ?? 0);
$adClicks = (int) ($adTotals['clicks'] ?? 0);
$hasAdData = $adImpressions > 0 || $adClicks > 0 || count($adEvents) > 0;
$ctr = $adImpressions > 0 ? round(($adClicks / $adImpressions) * 100, 2) : null;
$hasDownloadData = false;
$totalDownloads = 0;
foreach ($tracks as $trackForCount) {
    if (is_array($trackForCount) && (isset($trackForCount['downloads']) || isset($trackForCount['downloadCount']))) {
        $hasDownloadData = true;
        $totalDownloads += (int) ($trackForCount['downloads'] ?? $trackForCount['downloadCount'] ?? 0);
    }
}
$downloadCountFor = static function (array $track): int {
    return (int) ($track['downloads'] ?? $track['downloadCount'] ?? 0);
};
$adClickCountFor = static function (array $track) use ($adSongs): int {
    $id = (string) ($track['id'] ?? '');
    $songStats = is_array($adSongs[$id] ?? null) ? $adSongs[$id] : [];
    return (int) ($songStats['clicks'] ?? 0);
};
$statText = static function (?int $value, bool $hasData): string {
    return $hasData ? number_format((int) $value) : 'N/A';
};
$statSmallText = static function (?float $value, bool $hasData, string $suffix = ''): string {
    return $hasData && $value !== null ? rtrim(rtrim(number_format($value, 2), '0'), '.') . $suffix : 'N/A';
};
$topTracks = array_values(array_filter($tracks, 'is_array'));
usort($topTracks, static function ($a, $b) use ($downloadCountFor): int {
    return $downloadCountFor($b) <=> $downloadCountFor($a);
});
$topTracks = array_slice($topTracks, 0, 5);
$genreNames = array_values(array_filter(array_map(static fn ($genre): string => is_array($genre) ? (string) ($genre['name'] ?? '') : '', $genres)));
if ($genreNames === []) {
    $genreNames = ['Soundcheck', 'Marathi', 'Hindi', 'Original Mix'];
}
$genreUsage = [];
foreach ($genreNames as $genreName) {
    $genreUsage[$genreName] = 0;
}
foreach ($tracks as $track) {
    if (!is_array($track)) {
        continue;
    }
    $genreName = (string) ($track['genre'] ?? 'N/A');
    $genreUsage[$genreName] = ($genreUsage[$genreName] ?? 0) + 1;
}
arsort($genreUsage);
$genreTotal = array_sum($genreUsage);
$trackCount = count(array_filter($tracks, 'is_array'));
$artistCount = count(array_filter($artists, 'is_array'));
$latestTrack = is_array($tracks[0] ?? null) ? $tracks[0] : null;
$storageUsed = folderSize(__DIR__ . '/uploads');
$adMediaUrl = (string) ($advertising['mediaUrl'] ?? '');
$adMediaType = (string) ($advertising['mediaType'] ?? '');
$adEnabled = !empty($advertising['enabled']);
$adLinkUrl = (string) ($advertising['linkUrl'] ?? '');
$gridAd = is_array($advertising['gridAd'] ?? null) ? $advertising['gridAd'] : [];
$gridAdEnabled = !empty($gridAd['enabled']);
$gridAdImageUrl = (string) ($gridAd['imageUrl'] ?? '');
$gridAdName = (string) ($gridAd['name'] ?? '');
$gridAdSubtext = (string) ($gridAd['subtext'] ?? '');
$gridAdButtonText = (string) ($gridAd['buttonText'] ?? 'Learn more');
$gridAdButtonColor = (string) ($gridAd['buttonColor'] ?? '#ffffff');
$gridAdButtonTextColor = (string) ($gridAd['buttonTextColor'] ?? '#000000');
$gridAdLinkUrl = (string) ($gridAd['linkUrl'] ?? '');
$gridAdPosition = (int) ($gridAd['position'] ?? 8);
$todayIso = date('Y-m-d');
$weekAgoIso = date('Y-m-d', strtotime('-7 days'));
$downloadChartCounts = [];
$downloadChartLabels = [];

for ($offset = 7; $offset >= 0; $offset -= 1) {
    $dateKey = date('Y-m-d', strtotime('-' . $offset . ' days'));
    $downloadChartCounts[$dateKey] = 0;
    $downloadChartLabels[] = $offset === 0 ? 'Today' : date('M j', strtotime($dateKey));
}

foreach ($tracks as $track) {
    if (!is_array($track) || !is_array($track['downloadEvents'] ?? null)) {
        continue;
    }

    foreach ($track['downloadEvents'] as $downloadEvent) {
        if (!is_array($downloadEvent)) {
            continue;
        }

        $timestamp = (string) ($downloadEvent['timestamp'] ?? '');
        $dateKey = substr($timestamp, 0, 10);

        if (isset($downloadChartCounts[$dateKey])) {
            $downloadChartCounts[$dateKey] += 1;
        }
    }
}

$downloadChartRawValues = array_values($downloadChartCounts);
$hasDownloadChartData = array_sum($downloadChartRawValues) > 0;
$downloadChartMax = max(1, max($downloadChartRawValues));
$downloadChartX = [80, 194, 308, 422, 536, 650, 764, 875];
$downloadChartPoints = [];

foreach ($downloadChartRawValues as $index => $value) {
    $y = 238 - (int) round(($value / $downloadChartMax) * 184);
    $downloadChartPoints[] = $downloadChartX[$index] . ',' . $y;
}

$downloadChartData = [
    'points' => $hasDownloadChartData ? implode(' ', $downloadChartPoints) : '80,238 194,238 308,238 422,238 536,238 650,238 764,238 875,238',
    'values' => $hasDownloadChartData ? array_map(static fn (int $value): string => number_format($value), $downloadChartRawValues) : array_fill(0, 8, 'N/A'),
    'total' => $hasDownloadChartData ? number_format(array_sum($downloadChartRawValues)) : 'N/A',
    'label' => 'total downloads',
    'peak' => $hasDownloadChartData ? 'Peak: ' . $downloadChartLabels[array_search(max($downloadChartRawValues), $downloadChartRawValues, true)] . ' · ' . number_format(max($downloadChartRawValues)) : 'Peak: N/A',
];
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SG Production — Admin Studio</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>

  /* ════════════════════════════════════════════
     APPLE HIG — DARK MODE DESIGN SYSTEM
     • Semantic color layers (bg → surface → elevated)
     • 44×44pt minimum touch targets
     • Type scale: 11 / 13 / 15 / 17 / 20 / 28 / 34
     • 8pt spacing grid
     • Purposeful, subtle motion (≤ 300ms)
     • WCAG AA contrast on all text
  ════════════════════════════════════════════ */

  :root {
    /* — Semantic backgrounds (HIG layered system) — */
    --bg-primary:      #000000;          /* systemBackground */
    --bg-secondary:    #1c1c1e;          /* secondarySystemBackground */
    --bg-tertiary:     #2c2c2e;          /* tertiarySystemBackground */
    --bg-grouped:      #000000;          /* systemGroupedBackground */
    --bg-elevated:     #1c1c1e;          /* elevated surface */

    /* — Separator — */
    --separator:       rgba(84,84,88,0.65);
    --separator-opaque:#38383a;

    /* — Label colors (HIG) — */
    --label:           #ffffff;
    --label-secondary: rgba(235,235,245,0.6);
    --label-tertiary:  rgba(235,235,245,0.3);
    --label-quaternary:rgba(235,235,245,0.18);

    /* — Accent / tint — */
    --tint:            #0a84ff;          /* systemBlue dark */
    --tint-bg:         rgba(10,132,255,0.15);
    --tint-border:     rgba(10,132,255,0.4);

    /* — System colors — */
    --sys-green:       #2ebd6b;
    --sys-green-bg:    rgba(46,189,107,0.12);
    --sys-orange:      #ff9f0a;
    --sys-orange-bg:   rgba(255,159,10,0.12);
    --sys-red:         #ff453a;
    --sys-red-bg:      rgba(255,69,58,0.12);
    --sys-purple:      #bf5af2;
    --sys-purple-bg:   rgba(191,90,242,0.12);
    --sys-cyan:        #5ac8fa;
    --sys-cyan-bg:     rgba(90,200,250,0.12);
    --sys-yellow:      #ffd60a;

    /* — Spacing (8pt grid) — */
    --sp-1: 4px;
    --sp-2: 8px;
    --sp-3: 12px;
    --sp-4: 16px;
    --sp-5: 20px;
    --sp-6: 24px;
    --sp-8: 32px;

    /* — Radius — */
    --radius-sm:  8px;
    --radius-md:  12px;
    --radius-lg:  16px;
    --radius-xl:  20px;
    --radius-pill:999px;

    /* — Layout — */
    --sidebar-w: 240px;
    --topbar-h:  52px;

    /* — Motion — */
    --ease-out: cubic-bezier(0.2,0,0,1);
    --duration: 200ms;
  }

  *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

  html { -webkit-text-size-adjust: 100%; }

  body {
    font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", Inter, "Helvetica Neue", sans-serif;
    background: var(--bg-primary);
    color: var(--label);
    font-size: 15px;
    font-weight: 400;
    line-height: 1.47;
    -webkit-font-smoothing: antialiased;
    display: flex;
    height: 100vh;
    overflow: hidden;
  }

  /* ── SCROLLBAR ── */
  ::-webkit-scrollbar { width: 5px; height: 5px; }
  ::-webkit-scrollbar-track { background: transparent; }
  ::-webkit-scrollbar-thumb { background: var(--separator-opaque); border-radius: var(--radius-pill); }

  /* ════════════════════════
     SIDEBAR
  ════════════════════════ */
  .sidebar {
    width: var(--sidebar-w);
    min-width: var(--sidebar-w);
    background: var(--bg-secondary);
    border-right: 1px solid var(--separator);
    display: flex;
    flex-direction: column;
    overflow-y: auto;
    padding-bottom: var(--sp-4);
    z-index: 1;
  }

  .brand {
    padding: var(--sp-5) var(--sp-4) var(--sp-4);
    border-bottom: 1px solid var(--separator);
    display: flex;
    align-items: center;
    gap: var(--sp-3);
    min-height: var(--topbar-h);
  }

  .brand-avatar {
    width: 36px;
    height: 36px;
    min-width: 36px;
    border-radius: var(--radius-sm);
    background: var(--tint);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    font-weight: 700;
    color: #fff;
    letter-spacing: 0.02em;
  }

  .brand-text .name {
    font-size: 15px;
    font-weight: 600;
    color: var(--label);
    line-height: 1.2;
  }
  .brand-text .role {
    font-size: 11px;
    color: var(--label-secondary);
    margin-top: 2px;
    letter-spacing: 0.3px;
  }

  .nav-section { padding: var(--sp-3) var(--sp-2) var(--sp-1); }

  .nav-label {
    font-size: 11px;
    font-weight: 600;
    color: var(--label-tertiary);
    text-transform: uppercase;
    letter-spacing: 0.08em;
    padding: 0 var(--sp-2) var(--sp-1);
  }

  .nav-item {
    display: flex;
    align-items: center;
    gap: var(--sp-2);
    min-height: 44px;          /* HIG: 44pt touch target */
    padding: 0 var(--sp-3);
    border-radius: var(--radius-sm);
    font-size: 15px;
    font-weight: 400;
    color: var(--label-secondary);
    text-decoration: none;
    cursor: pointer;
    transition: background var(--duration) var(--ease-out),
                color var(--duration) var(--ease-out);
    position: relative;
  }

  .nav-item svg {
    width: 18px;
    height: 18px;
    flex-shrink: 0;
    opacity: 0.7;
    transition: opacity var(--duration) var(--ease-out);
  }

  .nav-item:hover {
    background: var(--bg-tertiary);
    color: var(--label);
  }
  .nav-item:hover svg { opacity: 1; }

  .nav-item.active {
    background: var(--tint-bg);
    color: var(--tint);
    font-weight: 600;
  }
  .nav-item.active svg { opacity: 1; }

  .sidebar-footer {
    margin-top: auto;
    padding: var(--sp-3) var(--sp-2) 0;
    border-top: 1px solid var(--separator);
  }

  .sidebar-scrim {
    position: fixed;
    inset: 0;
    z-index: 90;
    background: rgba(0,0,0,0.5);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    opacity: 0;
    pointer-events: none;
    transition: opacity var(--duration) var(--ease-out);
  }
  .sidebar-scrim.open { opacity: 1; pointer-events: auto; }

  /* ════════════════════════
     MAIN LAYOUT
  ════════════════════════ */
  .main {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    min-width: 0;
  }

  /* ════════════════════════
     TOPBAR
  ════════════════════════ */
  .topbar {
    height: var(--topbar-h);
    background: rgba(28,28,30,0.8);
    backdrop-filter: saturate(180%) blur(20px);
    -webkit-backdrop-filter: saturate(180%) blur(20px);
    border-bottom: 1px solid var(--separator);
    padding: 0 var(--sp-6);
    display: flex;
    align-items: center;
    gap: var(--sp-4);
    flex-shrink: 0;
    position: sticky;
    top: 0;
    z-index: 10;
  }

  .mobile-menu-toggle {
    width: 44px;
    height: 44px;
    border: none;
    border-radius: var(--radius-sm);
    background: transparent;
    color: var(--label);
    display: none;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    gap: 5px;
    cursor: pointer;
    flex-shrink: 0;
    transition: background var(--duration) var(--ease-out);
  }
  .mobile-menu-toggle:hover { background: var(--bg-tertiary); }
  .mobile-menu-toggle span {
    width: 18px;
    height: 1.5px;
    border-radius: var(--radius-pill);
    background: currentColor;
    display: block;
  }

  .topbar-title {
    font-size: 17px;
    font-weight: 600;
    color: var(--label);
    letter-spacing: -0.01em;
  }
  .topbar-sub {
    font-size: 13px;
    color: var(--label-secondary);
    margin-left: var(--sp-2);
  }
  .topbar-right { margin-left: auto; display: flex; align-items: center; gap: var(--sp-2); }

  /* ════════════════════════
     BUTTONS — HIG spec
     Primary: filled, tint color
     Secondary: gray fill
     Ghost: no fill, border
     Destructive: red
     Min height: 44pt
  ════════════════════════ */
  .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: var(--sp-2);
    min-height: 36px;
    padding: 0 var(--sp-4);
    border-radius: var(--radius-pill);
    border: none;
    font-family: inherit;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: opacity var(--duration) var(--ease-out),
                transform 100ms var(--ease-out),
                box-shadow var(--duration) var(--ease-out);
    white-space: nowrap;
    letter-spacing: -0.01em;
  }
  .btn:active { transform: scale(0.97); opacity: 0.85; }

  .btn-primary {
    background: var(--tint);
    color: #fff;
  }
  .btn-primary:hover { opacity: 0.88; }

  .btn-ghost {
    background: var(--bg-tertiary);
    color: var(--label-secondary);
    border: 1px solid var(--separator);
  }
  .btn-ghost:hover { background: var(--bg-elevated); color: var(--label); }

  .btn-outline {
    background: transparent;
    color: var(--tint);
    border: 1px solid var(--tint-border);
  }
  .btn-outline:hover { background: var(--tint-bg); }

  .btn-destructive {
    background: var(--sys-red-bg);
    color: var(--sys-red);
    border: 1px solid rgba(255,69,58,0.3);
  }

  /* ════════════════════════
     CONTENT AREA
  ════════════════════════ */
  .content {
    flex: 1;
    overflow-y: auto;
    padding: var(--sp-6);
    display: flex;
    flex-direction: column;
    gap: var(--sp-5);
  }

  .view-section { display: none; flex-direction: column; gap: var(--sp-5); }
  .view-section.active {
    display: flex;
    animation: hig-fade-up 280ms var(--ease-out) both;
  }

  /* ════════════════════════
     PANELS / CARDS
     HIG: grouped content uses
     rounded rect, layered bg
  ════════════════════════ */
  .panel {
    background: var(--bg-secondary);
    border: 1px solid var(--separator);
    border-radius: var(--radius-lg);
    padding: var(--sp-5);
    overflow: hidden;
  }

  .panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--sp-3);
    margin-bottom: var(--sp-4);
  }

  .panel-title {
    font-size: 15px;
    font-weight: 600;
    color: var(--label);
    letter-spacing: -0.01em;
  }

  .panel-action {
    font-size: 13px;
    font-weight: 500;
    color: var(--tint);
    background: none;
    border: none;
    cursor: pointer;
    font-family: inherit;
    padding: var(--sp-1) var(--sp-2);
    border-radius: var(--radius-sm);
    transition: background var(--duration) var(--ease-out);
  }
  .panel-action:hover { background: var(--tint-bg); }

  /* ════════════════════════
     STAT CARDS
  ════════════════════════ */
  .stats-grid {
    display: grid;
    grid-template-columns: repeat(4,1fr);
    gap: var(--sp-3);
  }

  .stat-card {
    background: var(--bg-secondary);
    border: 1px solid var(--separator);
    border-radius: var(--radius-lg);
    padding: var(--sp-5);
    position: relative;
    overflow: hidden;
    transition: transform var(--duration) var(--ease-out);
  }
  .stat-card:hover { transform: translateY(-1px); }

  /* HIG: use color purposefully, not decoratively */
  .stat-card::before {
    content:'';
    position:absolute;
    top:0; left:0; right:0;
    height:3px;
    border-radius: var(--radius-lg) var(--radius-lg) 0 0;
  }
  .stat-card.cyan::before  { background: var(--sys-cyan); }
  .stat-card.green::before { background: var(--sys-green); }
  .stat-card.orange::before{ background: var(--sys-orange); }
  .stat-card.purple::before{ background: var(--sys-purple); }

  .stat-icon { position:absolute; top:var(--sp-4); right:var(--sp-4); font-size:22px; opacity:0.12; }

  .stat-label {
    font-size: 11px;
    font-weight: 600;
    color: var(--label-secondary);
    text-transform: uppercase;
    letter-spacing: 0.07em;
    margin-bottom: var(--sp-2);
    margin-top: var(--sp-1);
  }

  .stat-value {
    font-size: 28px;
    font-weight: 700;
    letter-spacing: -0.03em;
    line-height: 1.1;
    margin-bottom: var(--sp-2);
  }
  .stat-value.cyan   { color: var(--sys-cyan); }
  .stat-value.green  { color: var(--sys-green); }
  .stat-value.orange { color: var(--sys-orange); }
  .stat-value.purple { color: var(--sys-purple); }

  .stat-change {
    font-size: 12px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: var(--sp-1);
  }
  .stat-change.up   { color: var(--sys-green); }
  .stat-change.down { color: var(--sys-red); }

  /* ════════════════════════
     GRID LAYOUTS
  ════════════════════════ */
  .two-col   { display:grid; grid-template-columns:1fr 1fr; gap:var(--sp-4); }
  .three-col { display:grid; grid-template-columns:1fr 1fr 1fr; gap:var(--sp-4); }
  .section-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:var(--sp-4); }

  /* ════════════════════════
     SONG TABLE
  ════════════════════════ */
  .song-table { width:100%; border-collapse:collapse; }
  .song-table th {
    text-align:left;
    font-size: 11px;
    font-weight: 600;
    color: var(--label-tertiary);
    text-transform: uppercase;
    letter-spacing: 0.07em;
    padding: 0 var(--sp-3) var(--sp-3);
    border-bottom: 1px solid var(--separator);
  }
  .song-table td {
    padding: var(--sp-3);
    border-bottom: 1px solid var(--separator);
    font-size: 13px;
    color: var(--label-secondary);
    vertical-align: middle;
  }
  .song-table tr:last-child td { border-bottom: none; }
  .song-table tr:hover td { background: var(--bg-tertiary); }

  .song-cover {
    width: 36px;
    height: 36px;
    border-radius: var(--radius-sm);
    background: var(--bg-tertiary);
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    flex-shrink: 0;
  }
  .song-cover img { width:100%; height:100%; object-fit:cover; display:block; }
  .song-info { display:flex; align-items:center; gap:var(--sp-3); }
  .song-name { font-size:13px; font-weight:600; color:var(--label); }
  .song-meta { font-size:11px; color:var(--label-tertiary); margin-top:2px; }

  /* ════════════════════════
     PILLS / BADGES
     HIG: use filled pills with
     semantic color bg + label
  ════════════════════════ */
  .pill {
    display: inline-flex;
    align-items: center;
    padding: 3px var(--sp-2);
    border-radius: var(--radius-pill);
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.02em;
  }
  .pill.green  { background: var(--sys-green-bg);  color: var(--sys-green); }
  .pill.orange { background: var(--sys-orange-bg); color: var(--sys-orange); }
  .pill.red    { background: var(--sys-red-bg);    color: var(--sys-red); }
  .pill.cyan   { background: var(--sys-cyan-bg);   color: var(--sys-cyan); }
  .pill.purple { background: var(--sys-purple-bg); color: var(--sys-purple); }

  /* ════════════════════════
     SONG ROWS (Songs section)
  ════════════════════════ */
  .songs-list { display:flex; flex-direction:column; gap:var(--sp-2); }

  .song-row {
    position: relative;
    display: grid;
    grid-template-columns: minmax(220px,1.4fr) 140px 110px 110px 110px 112px;
    gap: var(--sp-3);
    align-items: center;
    background: var(--bg-secondary);
    border: 1px solid var(--separator);
    border-radius: var(--radius-md);
    padding: var(--sp-3) var(--sp-4);
    transition: background var(--duration) var(--ease-out),
                border-color var(--duration) var(--ease-out);
  }
  .song-row:hover {
    background: var(--bg-tertiary);
    border-color: rgba(84,84,88,0.9);
  }

  .song-main { display:flex; align-items:center; gap:var(--sp-3); min-width:0; }

  .song-thumb {
    width: 52px;
    height: 52px;
    border-radius: var(--radius-sm);
    background: var(--bg-tertiary);
    border: 1px solid var(--separator);
    overflow: hidden;
    flex-shrink: 0;
  }
  .song-thumb img { width:100%; height:100%; object-fit:cover; display:block; }
  .song-title { font-size:15px; font-weight:600; color:var(--label); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .song-detail { font-size:12px; color:var(--label-secondary); margin-top:2px; }

  .metric-badge {
    display: inline-flex;
    align-items: center;
    white-space: nowrap;
    background: var(--bg-tertiary);
    border: 1px solid var(--separator);
    border-radius: var(--radius-pill);
    padding: 5px 10px;
    font-size: 12px;
    font-weight: 600;
    color: var(--label-secondary);
    min-width: 0;
  }

  .metric-badge.cyan   { color: var(--sys-cyan);   border-color: rgba(90,200,250,0.25); }
  .metric-badge.orange { color: var(--sys-orange); border-color: rgba(255,159,10,0.25); }

  .status-pill {
    display: inline-flex;
    justify-content: center;
    min-width: 76px;
    border-radius: var(--radius-pill);
    padding: 5px var(--sp-2);
    font-size: 12px;
    font-weight: 600;
  }
  .status-pill.published { background: var(--sys-green-bg);  color: var(--sys-green); }
  .status-pill.draft     { background: var(--sys-orange-bg); color: var(--sys-orange); }
  .status-pill.unlisted  { background: var(--sys-red-bg);    color: var(--sys-red); }

  .song-flags { display:flex; flex-wrap:wrap; gap:var(--sp-1); }
  .song-actions { display:flex; justify-content:flex-end; gap:var(--sp-2); }

  .icon-btn {
    width: 36px;
    height: 36px;
    min-width: 36px;
    border: 1px solid var(--separator);
    border-radius: var(--radius-sm);
    background: var(--bg-tertiary);
    color: var(--label-secondary);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: background var(--duration) var(--ease-out),
                color var(--duration) var(--ease-out),
                border-color var(--duration) var(--ease-out);
  }
  .icon-btn svg { width:15px; height:15px; }
  .icon-btn:hover { background: var(--bg-elevated); color: var(--label); }
  .icon-btn.danger:hover { background: var(--sys-red-bg); color: var(--sys-red); border-color: rgba(255,69,58,0.4); }

  .song-delete-warning {
    grid-column:1 / -1;
    display:none;
    align-items:center;
    justify-content:space-between;
    gap:var(--sp-4);
    border:1px solid var(--sys-red);
    border-radius:var(--radius-sm);
    background:var(--sys-red-bg);
    color:var(--label);
    padding:var(--sp-3);
    font-size:13px;
  }
  .song-row.confirming .song-delete-warning { display:flex; }

  /* ════════════════════════
     FORMS — HIG spec
     Labels above inputs
     Clear placeholder text
     Focus ring = tint color
  ════════════════════════ */
  .admin-form { display:flex; flex-direction:column; gap:var(--sp-5); }
  .form-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:var(--sp-4); }
  .form-field { display:flex; flex-direction:column; gap:var(--sp-1); }
  .form-field.full { grid-column:1 / -1; }

  .form-field label {
    font-size: 13px;
    font-weight: 600;
    color: var(--label-secondary);
    letter-spacing: 0.01em;
  }

  .form-field input,
  .form-field select,
  .form-field textarea {
    width: 100%;
    min-height: 44px;          /* HIG: 44pt touch target */
    background: var(--bg-tertiary);
    border: 1px solid var(--separator);
    border-radius: var(--radius-sm);
    color: var(--label);
    padding: var(--sp-3) var(--sp-4);
    font-family: inherit;
    font-size: 15px;           /* prevent iOS zoom */
    font-weight: 400;
    outline: none;
    transition: border-color var(--duration) var(--ease-out),
                box-shadow var(--duration) var(--ease-out);
    -webkit-appearance: none;
  }
  .form-field input::placeholder,
  .form-field textarea::placeholder { color: var(--label-quaternary); }
  .form-field input:focus,
  .form-field select:focus,
  .form-field textarea:focus {
    border-color: var(--tint);
    box-shadow: 0 0 0 3px var(--tint-bg);
  }

  .form-help {
    font-size: 12px;
    color: var(--label-tertiary);
    line-height: 1.5;
    margin-top: var(--sp-1);
  }

  .form-actions { display:flex; justify-content:flex-end; gap:var(--sp-2); padding-top:var(--sp-2); }

  .check-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:var(--sp-3); }
  .check-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--sp-3);
    background: var(--bg-tertiary);
    border: 1px solid var(--separator);
    border-radius: var(--radius-sm);
    padding: var(--sp-3) var(--sp-4);
    font-size: 13px;
    font-weight: 500;
    color: var(--label);
    cursor: pointer;
    min-height: 44px;
  }
  .check-card input[type="checkbox"] {
    width: 20px;
    height: 20px;
    min-height: auto;
    accent-color: var(--tint);
    border-radius: 4px;
  }

  /* Toggle switch — HIG style */
  .toggle-card { cursor:pointer; }
  .toggle-card input[type="checkbox"] {
    appearance: none;
    -webkit-appearance: none;
    width: 51px;
    height: 31px;
    min-height: auto;
    border-radius: var(--radius-pill);
    background: var(--bg-tertiary);
    border: none;
    position: relative;
    cursor: pointer;
    transition: background var(--duration) var(--ease-out);
  }
  .toggle-card input[type="checkbox"]::after {
    content:'';
    position:absolute;
    width:27px; height:27px;
    border-radius:50%;
    background:#fff;
    top:2px; left:2px;
    box-shadow:0 2px 6px rgba(0,0,0,0.4);
    transition:transform var(--duration) var(--ease-out);
  }
  .toggle-card input[type="checkbox"]:checked { background: var(--sys-green); }
  .toggle-card input[type="checkbox"]:checked::after { transform:translateX(20px); }

  /* ════════════════════════
     ARTIST GRID
  ════════════════════════ */
  .artist-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:var(--sp-4); }

  .artist-card {
    position: relative;
    background: var(--bg-secondary);
    border: 1px solid var(--separator);
    border-radius: var(--radius-lg);
    padding: var(--sp-6) var(--sp-4);
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    transition: background var(--duration) var(--ease-out),
                transform var(--duration) var(--ease-out);
    min-height: 200px;
    overflow: hidden;
  }
  .artist-card:hover {
    background: var(--bg-tertiary);
    transform: translateY(-2px);
  }

  .artist-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    border: 1px solid var(--separator);
    overflow: hidden;
    margin-bottom: var(--sp-3);
    background: var(--bg-tertiary);
    transition: border-color var(--duration) var(--ease-out);
  }
  .artist-card:hover .artist-avatar { border-color: var(--tint); }
  .artist-avatar img, .artist-preview img { width:100%; height:100%; object-fit:cover; display:block; }
  .artist-name { font-size:15px; font-weight:600; color:var(--label); }

  .artist-card-actions {
    position:absolute; inset:0;
    display:flex; align-items:center; justify-content:center; gap:var(--sp-2);
    background: rgba(0,0,0,0.6);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    opacity:0; pointer-events:none;
    border-radius: inherit;
    transition: opacity var(--duration) var(--ease-out);
  }
  .artist-card:hover .artist-card-actions { opacity:1; pointer-events:auto; }

  /* ════════════════════════
     GENRE GRID
  ════════════════════════ */
  .genre-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:var(--sp-4); }

  .genre-card {
    position: relative;
    background: var(--bg-secondary);
    border: 1px solid var(--separator);
    border-radius: var(--radius-lg);
    padding: var(--sp-4);
    overflow: hidden;
    transition: background var(--duration) var(--ease-out),
                transform var(--duration) var(--ease-out);
  }
  .genre-card:hover { background: var(--bg-tertiary); transform: translateY(-1px); }
  .genre-card::before {
    content:''; position:absolute; top:0; left:0; right:0; height:3px;
    background: var(--genre-color, var(--tint));
  }
  .genre-card-head { display:flex; align-items:flex-start; justify-content:space-between; gap:var(--sp-3); margin-bottom:var(--sp-2); }
  .genre-card-title { font-size:17px; font-weight:600; color:var(--label); }
  .genre-slug { font-size:12px; color:var(--label-tertiary); margin-top:2px; }
  .genre-card-actions { display:flex; gap:var(--sp-2); flex-shrink:0; }
  .genre-description { font-size:13px; color:var(--label-secondary); line-height:1.5; margin:var(--sp-3) 0; min-height:36px; }
  .genre-counts { display:flex; flex-wrap:wrap; gap:var(--sp-2); }
  .genre-confirm {
    display:none; flex-direction:column; gap:var(--sp-3); margin-top:var(--sp-4);
    border:1px solid var(--sys-orange); border-radius:var(--radius-sm);
    background:var(--sys-orange-bg); color:var(--label); padding:var(--sp-3); font-size:13px;
  }
  .genre-card.confirming .genre-confirm { display:flex; }
  .genre-confirm.error { display:flex; border-color:var(--sys-red); background:var(--sys-red-bg); color:var(--sys-red); }
  .genre-confirm-actions { display:flex; flex-wrap:wrap; gap:var(--sp-2); }
  .genre-form-actions { display:flex; justify-content:flex-end; gap:var(--sp-2); flex-wrap:wrap; }
  [data-cancel-genre-edit] { display:none; }
  .is-editing-genre [data-cancel-genre-edit] { display:inline-flex; }

  /* ════════════════════════
     TAGS / CHIPS
  ════════════════════════ */
  .tag-input-shell {
    min-height: 44px;
    background: var(--bg-tertiary);
    border: 1px solid var(--separator);
    border-radius: var(--radius-sm);
    padding: var(--sp-2);
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: var(--sp-2);
    transition: border-color var(--duration) var(--ease-out), box-shadow var(--duration) var(--ease-out);
  }
  .tag-input-shell:focus-within { border-color: var(--tint); box-shadow:0 0 0 3px var(--tint-bg); }
  .tag-input-shell input {
    min-width: 140px; flex:1; border:0; background:transparent;
    color:var(--label); padding:var(--sp-1) var(--sp-2); outline:0; font:inherit; font-size:15px;
    min-height:auto;
  }
  .genre-tag, .artist-genre-pill {
    display:inline-flex; align-items:center; gap:var(--sp-1);
    background: var(--tint-bg); border:1px solid var(--tint-border);
    color:var(--tint); border-radius:var(--radius-pill);
    padding: 3px var(--sp-2); font-size:12px; font-weight:600;
  }
  .genre-tag button {
    width:16px; height:16px; border:0; border-radius:50%;
    background:rgba(255,255,255,0.1); color:inherit; cursor:pointer; line-height:1;
  }

  /* ════════════════════════
     UPLOAD SECTION
  ════════════════════════ */
  .upload-layout { display:grid; grid-template-columns:1fr 300px; gap:var(--sp-4); align-items:start; }
  .upload-main-card { min-width:0; }
  .upload-panel { max-width:1040px; }
  .section-note { color:var(--label-secondary); line-height:1.6; font-size:15px; }

  .image-preview-box {
    background: var(--bg-tertiary);
    border: 1px solid var(--separator);
    border-radius: var(--radius-md);
    padding: var(--sp-5);
    display:flex; flex-direction:column; align-items:center; gap:var(--sp-4);
  }

  .artist-preview {
    width: 100px; height: 100px; border-radius:50%;
    border:1px solid var(--separator);
    background:var(--bg-tertiary); overflow:hidden;
  }

  .file-trigger {
    width: 100%;
    min-height: 44px;
    border: 1px dashed rgba(10,132,255,0.45);
    border-radius: var(--radius-sm);
    background: var(--tint-bg);
    cursor: pointer;
    color: var(--tint);
    font-family: inherit;
    font-size: 13px;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 var(--sp-4);
    transition: background var(--duration) var(--ease-out),
                border-color var(--duration) var(--ease-out),
                transform 100ms var(--ease-out);
    /* Prevent any click bleeding — button is self-contained */
    pointer-events: auto;
    position: relative;
    z-index: 1;
    -webkit-appearance: none;
    appearance: none;
  }
  .file-trigger:hover {
    background: rgba(10,132,255,0.2);
    border-color: var(--tint);
    border-style: solid;
  }
  .file-trigger:active { transform: scale(0.98); }
  /* Hidden inputs are completely non-interactive visually */
  input[type="file"][style*="display:none"] {
    pointer-events: none;
    position: absolute;
    width: 0; height: 0;
    overflow: hidden;
    opacity: 0;
  }

  .selected-file-name {
    max-width:100%; color:var(--label-secondary); font-size:12px;
    font-weight:500; text-align:center; overflow:hidden;
    text-overflow:ellipsis; white-space:nowrap;
  }

  .duration-pill {
    display:inline-flex; align-items:center; min-height:44px; width:100%;
    border:1px solid var(--separator); border-radius:var(--radius-sm);
    background:var(--bg-tertiary); color:var(--label-secondary);
    padding:var(--sp-3) var(--sp-4); font-size:13px; font-weight:600;
  }

  .char-row { display:flex; justify-content:space-between; gap:var(--sp-3); }
  .char-count { color:var(--label-tertiary); font-size:12px; font-weight:500; }

  .cover-upload-row { display:grid; grid-template-columns:88px 1fr; gap:var(--sp-4); align-items:center; }
  .cover-preview-thumb {
    width:88px; aspect-ratio:1;
    border:1px solid var(--separator); border-radius:var(--radius-sm);
    background:var(--bg-tertiary); overflow:hidden;
    display:grid; place-items:center; color:var(--label-tertiary);
    font-size:11px; font-weight:600;
  }
  .cover-preview-thumb img { width:100%; height:100%; object-fit:cover; display:block; }

  .file-meta {
    display:none; align-items:center; justify-content:space-between;
    gap:var(--sp-3); border:1px solid var(--separator); border-radius:var(--radius-sm);
    background:var(--tint-bg); color:var(--label-secondary);
    padding:var(--sp-2) var(--sp-3); font-size:12px; font-weight:500;
  }
  .file-meta.is-visible { display:flex; }

  .upload-progress {
    display:none; border:1px solid var(--separator); border-radius:var(--radius-md);
    background:var(--bg-tertiary); padding:var(--sp-4);
  }
  .upload-progress.is-visible { display:block; }
  .progress-head { display:flex; justify-content:space-between; color:var(--label-secondary); font-size:12px; font-weight:600; margin-bottom:var(--sp-2); }
  .progress-track { height:6px; border-radius:var(--radius-pill); background:var(--bg-elevated); overflow:hidden; }
  .progress-fill { width:0%; height:100%; border-radius:inherit; background:var(--tint); transition:width 0.18s; }

  .tips-card ul { list-style:none; display:flex; flex-direction:column; gap:var(--sp-4); color:var(--label-secondary); font-size:13px; line-height:1.5; }
  .tips-card li { border-bottom:1px solid var(--separator); padding-bottom:var(--sp-4); }
  .tips-card li:last-child { border-bottom:0; padding-bottom:0; }

  /* ════════════════════════
     MANAGEMENT TOOLBARS
  ════════════════════════ */
  .management-toolbar { display:flex; align-items:flex-start; justify-content:space-between; gap:var(--sp-5); }
  .management-toolbar h2 { font-size:20px; font-weight:700; line-height:1.2; margin-bottom:var(--sp-1); letter-spacing:-0.02em; }
  .management-toolbar p { color:var(--label-secondary); font-size:14px; }

  .artist-toolbar { display:flex; align-items:flex-start; justify-content:space-between; gap:var(--sp-5); }
  .artist-toolbar h2 { font-size:20px; font-weight:700; line-height:1.2; margin-bottom:var(--sp-1); letter-spacing:-0.02em; }
  .artist-toolbar p { color:var(--label-secondary); font-size:14px; }

  .artist-form-layout { display:grid; grid-template-columns:1fr 220px; gap:var(--sp-5); align-items:start; }

  .song-controls { display:flex; align-items:center; justify-content:flex-end; gap:var(--sp-2); flex-wrap:wrap; }

  .admin-control {
    min-height: 36px;
    border:1px solid var(--separator); border-radius:var(--radius-pill);
    background:var(--bg-tertiary); color:var(--label);
    padding:var(--sp-2) var(--sp-3);
    font:inherit; font-size:13px; font-weight:500; outline:0;
    transition: border-color var(--duration) var(--ease-out), box-shadow var(--duration) var(--ease-out);
  }
  .admin-control:focus { border-color:var(--tint); box-shadow:0 0 0 3px var(--tint-bg); }
  .song-search { width:220px; }

  /* ════════════════════════
     SETTINGS
  ════════════════════════ */
  .settings-stack { display:flex; flex-direction:column; gap:var(--sp-4); padding-bottom:80px; }
  .settings-card-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:var(--sp-4); }
  .settings-preview {
    width:120px; aspect-ratio:1.91/1;
    border:1px solid var(--separator); border-radius:var(--radius-sm);
    background:var(--bg-tertiary); overflow:hidden;
    display:grid; place-items:center; color:var(--label-tertiary); font-size:12px; font-weight:600;
  }
  .settings-preview.square { width:64px; aspect-ratio:1; border-radius:var(--radius-md); }
  .settings-preview img { width:100%; height:100%; object-fit:cover; display:block; }
  .settings-upload-row { display:flex; align-items:center; gap:var(--sp-4); }

  /* Compact colour picker */
  input[type="color"] {
    -webkit-appearance: none;
    appearance: none;
    width: 40px;
    height: 40px;
    padding: 3px;
    border: 2px solid rgba(255,255,255,0.12);
    border-radius: 10px;
    background: transparent;
    cursor: pointer;
    flex-shrink: 0;
  }
  input[type="color"]::-webkit-color-swatch-wrapper { padding: 0; }
  input[type="color"]::-webkit-color-swatch { border: none; border-radius: 7px; }
  input[type="color"]::-moz-color-swatch { border: none; border-radius: 7px; }
  input[type="color"]:hover { border-color: rgba(255,255,255,0.28); }
  .form-field:has(input[type="color"]) {
    flex-direction: row;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
  }
  .form-field:has(input[type="color"]) > span,
  .form-field:has(input[type="color"]) > label { flex: 1; }

  .color-swatch-grid { display:flex; flex-wrap:wrap; gap:var(--sp-2); }
  .color-swatch-option {
    width:36px; height:36px; border-radius:var(--radius-pill);
    border:2px solid transparent; padding:3px;
    cursor:pointer; display:grid; place-items:center;
  }
  .color-swatch-option input { display:none; }
  .color-swatch-option span {
    width:100%; height:100%; border-radius:inherit;
    background:var(--swatch);
  }
  .color-swatch-option:has(input:checked) { border-color:var(--label); }

  /* ════════════════════════
     STICKY SAVE BAR
  ════════════════════════ */
  .sticky-save-bar {
    position:sticky; bottom:0; z-index:20;
    margin-top:var(--sp-1);
    border:1px solid var(--separator);
    border-radius:var(--radius-xl);
    background: rgba(28,28,30,0.9);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    padding: var(--sp-3) var(--sp-5);
    display:flex; align-items:center; justify-content:space-between; gap:var(--sp-4);
  }
  .save-copy { color:var(--label-secondary); font-size:13px; }

  /* ════════════════════════
     ANALYTICS
  ════════════════════════ */
  .analytics-filter-bar { display:flex; align-items:center; justify-content:space-between; gap:var(--sp-4); flex-wrap:wrap; }

  .date-chip-group {
    display:flex; gap:var(--sp-1); padding:3px;
    border:1px solid var(--separator); border-radius:var(--radius-pill);
    background:var(--bg-secondary);
    align-items:center;
  }
  .date-chip {
    border:0; border-radius:var(--radius-pill);
    background:transparent; color:var(--label-secondary);
    padding:var(--sp-2) var(--sp-4); font:inherit; font-size:12px; font-weight:600; cursor:pointer;
    transition:background var(--duration) var(--ease-out), color var(--duration) var(--ease-out);
    min-height:32px;
  }
  .date-chip:hover { background: rgba(255,255,255,0.04); color:var(--label); }
  .date-chip.active {
    background: var(--tint); color:#fff;
  }

  .custom-date-range { display:flex; align-items:center; gap:var(--sp-2); flex-wrap:wrap; justify-content:flex-end; }
  .range-field {
    display:flex; align-items:center; gap:var(--sp-2);
    border:1px solid var(--separator); border-radius:var(--radius-pill);
    background:var(--bg-secondary); padding:4px var(--sp-2) 4px var(--sp-3);
  }
  .range-field span { color:var(--label-tertiary); font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.5px; }
  .range-field .admin-control { width:150px; min-height:30px; border:0; border-radius:var(--radius-pill); background:var(--bg-tertiary); padding:5px var(--sp-2); }
  .date-range-apply { min-height:38px; padding-inline:var(--sp-4); }

  .analytics-kpis { display:grid; grid-template-columns:repeat(6,1fr); gap:var(--sp-3); }

  .line-chart-shell {
    position:relative; min-height:320px;
    border:1px solid var(--separator); border-radius:var(--radius-lg);
    background:var(--bg-secondary); padding:var(--sp-5); overflow:hidden;
  }
  .chart-summary { display:flex; align-items:center; justify-content:space-between; gap:var(--sp-3); margin-bottom:var(--sp-3); color:var(--label-secondary); font-size:12px; font-weight:600; flex-wrap:wrap; }
  .chart-summary strong { color:var(--label); font-size:15px; }
  .line-chart-shell svg { width:100%; height:100%; display:block; }
  .line-chart-shell svg line { vector-effect:non-scaling-stroke; }
  .line-chart-shell svg polyline { vector-effect:non-scaling-stroke; stroke-width:2; }
  .chart-visual { position:relative; height:240px; }
  .chart-y-axis { position:absolute; inset:0; z-index:2; pointer-events:none; }
  .chart-y-axis span { position:absolute; left:0; transform:translateY(-50%); color:var(--label-tertiary); font-size:10px; font-weight:600; }
  .chart-point-layer { position:absolute; inset:0; z-index:3; }
  .chart-point {
    position:absolute; width:28px; height:28px;
    border:0; border-radius:50%; background:transparent;
    transform:translate(-50%,-50%); pointer-events:auto; cursor:pointer; padding:0;
  }
  .chart-point::before {
    content:''; position:absolute; left:50%; top:50%;
    width:10px; height:10px; border-radius:50%;
    background:var(--tint); box-shadow:0 0 0 4px var(--tint-bg);
    transform:translate(-50%,-50%);
    transition:box-shadow var(--duration) var(--ease-out), transform 100ms var(--ease-out);
  }
  .chart-point:hover::before { box-shadow:0 0 0 7px var(--tint-bg); transform:translate(-50%,-50%) scale(1.1); }
  .chart-point-value {
    position:absolute; left:50%; bottom:28px; transform:translateX(-50%);
    border:1px solid var(--separator); border-radius:var(--radius-pill);
    padding:3px var(--sp-2); color:var(--label); background:var(--bg-secondary);
    box-shadow:0 4px 12px rgba(0,0,0,0.3); font-size:11px; font-weight:600;
    opacity:0; white-space:nowrap; transition:opacity var(--duration) var(--ease-out);
    pointer-events:none;
  }
  .chart-point:hover .chart-point-value { opacity:1; }
  .chart-axis { display:flex; justify-content:space-between; color:var(--label-tertiary); font-size:11px; font-weight:600; padding:var(--sp-2) var(--sp-2) 0 64px; }
  .chart-value-label { display:none; }
  .chart-toggle {
    display:flex; gap:var(--sp-1); padding:3px;
    border:1px solid var(--separator); border-radius:var(--radius-pill);
    background:var(--bg-secondary);
    flex-wrap:wrap;
  }
  .chart-toggle button {
    flex:0 0 auto;
    min-width:0;
    border:0;
    border-radius:var(--radius-pill);
    background:transparent;
    color:var(--label-secondary);
    padding:var(--sp-2) var(--sp-4);
    font:inherit; font-size:12px; font-weight:600; cursor:pointer;
    min-height:32px;
    transition:background var(--duration) var(--ease-out), color var(--duration) var(--ease-out);
  }
  .chart-toggle button.active {
    background:var(--tint);
    color:#fff;
  }
  .chart-toggle button:hover:not(.active) {
    background:var(--tint-bg);
    color:var(--tint);
  }

  .horizontal-bars { display:flex; flex-direction:column; gap:var(--sp-4); }
  .hbar-row { display:grid; grid-template-columns:130px 1fr 48px; gap:var(--sp-3); align-items:center; }
  .hbar-label { color:var(--label); font-size:13px; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .hbar-track { height:8px; border-radius:var(--radius-pill); background:var(--bg-tertiary); overflow:hidden; }
  .hbar-fill { height:100%; border-radius:inherit; background:var(--tint); }
  .hbar-value { color:var(--label-secondary); font-size:12px; font-weight:600; text-align:right; }

  .donut-wrap { display:grid; grid-template-columns:160px 1fr; gap:var(--sp-6); align-items:center; }
  .donut-chart {
    width:160px; aspect-ratio:1; border-radius:50%;
    background:conic-gradient(
      #00d4ff 0 38%,
      #2ebd6b 38% 64%,
      #ff9f43 64% 84%,
      #a855f7 84% 100%
    );
    position:relative;
  }
  .donut-chart::after {
    content:''; position:absolute; inset:32px; border-radius:50%;
    background:var(--bg-secondary); border:1px solid var(--separator);
  }
  .donut-legend { display:flex; flex-direction:column; gap:var(--sp-3); }
  .legend-row { display:flex; align-items:center; justify-content:space-between; gap:var(--sp-3); color:var(--label-secondary); font-size:13px; font-weight:600; }
  .legend-label { display:flex; align-items:center; gap:var(--sp-2); }
  .legend-dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; }

  .analytics-table-wrap { overflow:auto; }
  .analytics-table { width:100%; min-width:1100px; border-collapse:collapse; }
  .analytics-table th, .analytics-table td { padding:var(--sp-3) var(--sp-2); border-bottom:1px solid var(--separator); text-align:left; vertical-align:middle; }
  .analytics-table th { color:var(--label-tertiary); font-size:11px; text-transform:uppercase; letter-spacing:.07em; font-weight:600; white-space:nowrap; }
  .analytics-table th[data-sort] { cursor:pointer; }
  .analytics-table th[data-sort]::after { content:' ↕'; color:var(--label-quaternary); }
  .analytics-table td { color:var(--label-secondary); font-size:13px; font-weight:500; }
  .analytics-table tr:hover td { background:var(--bg-tertiary); }
  .table-cover { width:40px; height:40px; border-radius:var(--radius-sm); overflow:hidden; border:1px solid var(--separator); }
  .table-cover img { width:100%; height:100%; object-fit:cover; display:block; }

  /* ════════════════════════
     NOTIFICATIONS
  ════════════════════════ */
  .notification-tabs { display:flex; gap:var(--sp-2); flex-wrap:wrap; }
  .notification-feed { display:flex; flex-direction:column; gap:var(--sp-2); }
  .notification-item {
    position:relative;
    display:grid; grid-template-columns:44px 1fr auto;
    gap:var(--sp-3); align-items:center;
    border:1px solid var(--separator);
    border-left-width:3px;
    border-radius:var(--radius-md);
    background:var(--bg-secondary); padding:var(--sp-3) var(--sp-4);
    transition:background var(--duration) var(--ease-out);
  }
  .notification-item:hover { background:var(--bg-tertiary); }
  .notification-item.download { border-left-color:var(--sys-cyan); }
  .notification-item.ad       { border-left-color:var(--sys-green); }
  .notification-item.warning  { border-left-color:var(--sys-orange); }
  .notification-item.error    { border-left-color:var(--sys-red); }
  .notification-item.system   { border-left-color:var(--sys-purple); }
  .notification-icon { width:44px; height:44px; border-radius:50%; display:grid; place-items:center; background:var(--bg-tertiary); color:var(--tint); border:1px solid var(--separator); }
  .notification-icon svg { width:20px; height:20px; }
  .notification-title-row { display:flex; align-items:center; gap:var(--sp-2); margin-bottom:3px; }
  .notification-title { font-size:15px; font-weight:600; color:var(--label); }
  .unread-dot { width:8px; height:8px; border-radius:50%; background:var(--tint); }
  .notification-description { color:var(--label-secondary); font-size:13px; line-height:1.45; }
  .notification-time { color:var(--label-tertiary); font-size:12px; font-weight:500; margin-top:4px; }
  .dismiss-notification { opacity:0; pointer-events:none; transition:opacity var(--duration) var(--ease-out); }
  .notification-item:hover .dismiss-notification { opacity:1; pointer-events:auto; }
  .notification-settings { overflow:hidden; }
  .notification-settings-body { display:none; padding-top:var(--sp-4); }
  .notification-settings.open .notification-settings-body { display:block; }

  /* ════════════════════════
     ADVERTISING
  ════════════════════════ */
  .ad-status-layout { display:grid; grid-template-columns:140px 1fr 220px; gap:var(--sp-5); align-items:center; }
  .ad-preview-thumb {
    width:140px; aspect-ratio:9/16;
    border:1px solid var(--separator); border-radius:var(--radius-md);
    overflow:hidden; background:var(--bg-tertiary);
    display:grid; place-items:center; color:var(--label-tertiary); font-size:12px; font-weight:600;
  }
  .ad-preview-thumb img, .ad-preview-thumb video { width:100%; height:100%; object-fit:cover; display:block; }
  .ad-detail-list { display:flex; flex-direction:column; gap:var(--sp-2); color:var(--label-secondary); font-size:13px; }
  .ad-detail-list strong { color:var(--label); }
  .ad-upload-layout { display:grid; grid-template-columns:1fr 180px; gap:var(--sp-5); align-items:start; }
  .ad-media-meta {
    display:none; flex-direction:column; gap:var(--sp-2);
    border:1px solid var(--separator); border-radius:var(--radius-sm);
    background:var(--bg-tertiary); padding:var(--sp-3);
    color:var(--label-secondary); font-size:12px; font-weight:600;
  }
  .ad-media-meta.is-visible { display:flex; }
  .ratio-warning {
    display:none; border:1px solid var(--sys-orange); border-radius:var(--radius-sm);
    background:var(--sys-orange-bg); color:var(--sys-orange); padding:var(--sp-2) var(--sp-3);
    font-size:12px; font-weight:600;
  }
  .ratio-warning.is-visible { display:block; }
  .ad-summary-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:var(--sp-3); }

  .history-table { width:100%; border-collapse:collapse; min-width:960px; }
  .history-table th, .history-table td { padding:var(--sp-3) var(--sp-2); border-bottom:1px solid var(--separator); text-align:left; vertical-align:middle; }
  .history-table th { color:var(--label-tertiary); font-size:11px; text-transform:uppercase; letter-spacing:.07em; font-weight:600; }
  .history-table td { color:var(--label-secondary); font-size:13px; font-weight:500; }

  /* ════════════════════════
     FILTER BAR
  ════════════════════════ */
  .filter-bar { display:flex; align-items:center; gap:var(--sp-2); flex-wrap:wrap; }
  .filter-chip {
    padding:var(--sp-1) var(--sp-3); border-radius:var(--radius-pill);
    border:1px solid var(--separator); font-size:12px; font-weight:500; cursor:pointer;
    color:var(--label-secondary); background:transparent;
    transition:all var(--duration) var(--ease-out);
    min-height:32px;
  }
  .filter-chip:hover { border-color:rgba(84,84,88,0.9); color:var(--label); }
  .filter-chip.active { border-color:var(--tint-border); color:var(--tint); background:var(--tint-bg); }
  .filter-sep { color:var(--label-quaternary); font-size:11px; }

  /* ════════════════════════
     QUICK ACTIONS
  ════════════════════════ */
  .quick-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:var(--sp-2); }
  .quick-card {
    background:var(--bg-tertiary); border:1px solid var(--separator);
    border-radius:var(--radius-md); padding:var(--sp-4) var(--sp-3);
    cursor:pointer; text-align:center;
    display:flex; flex-direction:column; align-items:center; gap:var(--sp-2);
    color:inherit; font:inherit; min-height:80px;
    transition:background var(--duration) var(--ease-out),
               border-color var(--duration) var(--ease-out),
               transform 100ms var(--ease-out);
  }
  .quick-card:hover { background:var(--bg-elevated); border-color:rgba(84,84,88,0.9); }
  .quick-card:active { transform:scale(0.97); }
  .quick-card .icon { font-size:22px; }
  .quick-card .label { font-size:11px; font-weight:600; color:var(--label-secondary); letter-spacing:0.02em; line-height:1.3; }
  .quick-card:hover .label { color:var(--label); }

  /* ════════════════════════
     ACTIVITY FEED
  ════════════════════════ */
  .activity-item { display:flex; gap:var(--sp-3); padding:var(--sp-3) 0; border-bottom:1px solid var(--separator); }
  .activity-item:last-child { border-bottom:none; }
  .activity-dot { width:8px; height:8px; border-radius:50%; margin-top:5px; flex-shrink:0; }
  .activity-text { font-size:13px; color:var(--label-secondary); line-height:1.5; }
  .activity-text strong { color:var(--label); font-weight:600; }
  .activity-time { font-size:11px; color:var(--label-tertiary); margin-top:2px; }

  /* ════════════════════════
     MINI BAR CHART
  ════════════════════════ */
  .bar-chart { display:flex; align-items:flex-end; gap:4px; height:48px; }
  .bar { flex:1; border-radius:3px 3px 0 0; background:var(--tint-bg); position:relative; transition:background var(--duration) var(--ease-out); cursor:pointer; }
  .bar:hover { background:var(--tint); }
  .bar span { position:absolute; bottom:-18px; left:50%; transform:translateX(-50%); font-size:9px; color:var(--label-tertiary); white-space:nowrap; }
  .chart-wrap { padding-bottom:22px; }

  /* ════════════════════════
     AD STATS
  ════════════════════════ */
  .ad-stat-row { display:flex; align-items:center; justify-content:space-between; padding:var(--sp-3) 0; border-bottom:1px solid var(--separator); }
  .ad-stat-row:last-child { border-bottom:none; }
  .ad-stat-label { font-size:13px; color:var(--label-secondary); display:flex; align-items:center; gap:var(--sp-2); }
  .ad-stat-label .dot { width:6px; height:6px; border-radius:50%; flex-shrink:0; }
  .ad-stat-val { font-size:13px; font-weight:600; color:var(--label); }
  .prog-bar { height:4px; background:var(--bg-tertiary); border-radius:2px; overflow:hidden; margin-top:4px; }
  .prog-fill { height:100%; border-radius:2px; }

  /* ════════════════════════
     EXPORT BOX
  ════════════════════════ */
  .export-box {
    background:var(--bg-tertiary); border:1px solid var(--separator);
    border-radius:var(--radius-md); padding:var(--sp-5);
    display:flex; align-items:center; justify-content:space-between; gap:var(--sp-4);
  }
  .export-box-text .title { font-size:14px; font-weight:600; margin-bottom:3px; color:var(--label); }
  .export-box-text .sub { font-size:12px; color:var(--label-secondary); }
  .export-btns { display:flex; gap:var(--sp-2); flex-shrink:0; }

  /* ════════════════════════
     TABS
  ════════════════════════ */
  .tabs { display:flex; gap:2px; background:var(--bg-tertiary); padding:3px; border-radius:var(--radius-sm); }
  .tab {
    padding:var(--sp-1) var(--sp-4); border-radius:var(--radius-sm);
    font-size:13px; font-weight:500; cursor:pointer;
    color:var(--label-secondary); border:none; background:transparent; font-family:inherit;
    min-height:32px;
    transition:background var(--duration) var(--ease-out), color var(--duration) var(--ease-out);
  }
  .tab.active { background:var(--bg-secondary); color:var(--label); font-weight:600; box-shadow:0 1px 4px rgba(0,0,0,0.3); }

  /* ════════════════════════
     NOTIFICATION DOT
  ════════════════════════ */
  .notif { position:relative; }
  .notif::after {
    content:''; position:absolute; top:4px; right:4px;
    width:7px; height:7px; border-radius:50%;
    background:var(--sys-red); border:1.5px solid var(--bg-secondary);
  }

  /* ════════════════════════
     PAGINATION
  ════════════════════════ */
  .pagination { display:flex; align-items:center; justify-content:center; gap:var(--sp-2); padding-top:var(--sp-2); }
  .page-btn {
    min-width:36px; height:36px;
    border:1px solid var(--separator); border-radius:var(--radius-pill);
    background:var(--bg-secondary); color:var(--label-secondary); cursor:pointer;
    font-weight:600; font-size:13px; font-family:inherit;
    transition:background var(--duration) var(--ease-out), color var(--duration) var(--ease-out), border-color var(--duration) var(--ease-out);
  }
  .page-btn.active, .page-btn:hover { background:var(--tint); color:#fff; border-color:var(--tint); }

  /* ════════════════════════
     DRAWER
  ════════════════════════ */
  .drawer-backdrop { position:fixed; inset:0; z-index:70; background:rgba(0,0,0,0.4); opacity:0; pointer-events:none; transition:opacity var(--duration) var(--ease-out); }
  .drawer-backdrop.open { opacity:1; pointer-events:auto; }
  .edit-drawer {
    position:fixed; inset:0 0 0 auto; z-index:80;
    width:min(500px, calc(100vw - 24px));
    background:var(--bg-secondary);
    border-left:1px solid var(--separator);
    box-shadow:-16px 0 48px rgba(0,0,0,0.4);
    transform:translateX(105%);
    transition:transform 280ms var(--ease-out);
    display:flex; flex-direction:column;
  }
  .edit-drawer.open { transform:translateX(0); }
  .drawer-head { padding:var(--sp-5); border-bottom:1px solid var(--separator); display:flex; align-items:flex-start; justify-content:space-between; gap:var(--sp-5); }
  .drawer-head h3 { font-size:20px; font-weight:700; margin-bottom:3px; letter-spacing:-0.02em; }
  .drawer-head p { color:var(--label-secondary); font-size:13px; }
  .drawer-body { padding:var(--sp-5); overflow-y:auto; }

  /* ════════════════════════
     TOAST — HIG spec
     Short, non-intrusive
     Auto-dismiss
  ════════════════════════ */
  .toast {
    position:fixed; right:var(--sp-6); bottom:var(--sp-6); z-index:120;
    background:var(--bg-secondary);
    border:1px solid var(--separator);
    color:var(--label);
    border-radius:var(--radius-pill);
    padding:var(--sp-3) var(--sp-5);
    box-shadow:0 8px 32px rgba(0,0,0,0.4);
    opacity:0; transform:translateY(8px) scale(0.97);
    pointer-events:none;
    transition:opacity var(--duration) var(--ease-out), transform var(--duration) var(--ease-out);
    font-weight:600; font-size:13px;
    backdrop-filter:blur(20px);
    -webkit-backdrop-filter:blur(20px);
  }
  .toast.show { opacity:1; transform:translateY(0) scale(1); }

  /* ════════════════════════
     EMPTY STATE
  ════════════════════════ */
  .empty-state {
    display:none; min-height:200px;
    border:1px dashed var(--separator); border-radius:var(--radius-lg);
    background:rgba(255,255,255,0.02);
    align-items:center; justify-content:center; text-align:center; padding:var(--sp-8);
  }
  .empty-state.is-visible { display:flex; flex-direction:column; }
  .empty-state h3 { font-size:17px; font-weight:600; margin-bottom:var(--sp-2); }
  .empty-state p { color:var(--label-secondary); margin-bottom:var(--sp-5); font-size:15px; }

  /* ════════════════════════
     MISC
  ════════════════════════ */
  .mono { font-variant-numeric:tabular-nums; }
  .section-note { color:var(--label-secondary); line-height:1.6; max-width:720px; font-size:15px; }

  /* ════════════════════════
     ANIMATIONS
     HIG: brief, eased, purposeful
  ════════════════════════ */
  @keyframes hig-fade-up {
    from { opacity:0; transform:translateY(10px); }
    to   { opacity:1; transform:translateY(0); }
  }

  /* ════════════════════════
     RESPONSIVE — HIG adaptive
  ════════════════════════ */

  @media (max-width:1180px) {
    .stats-grid { grid-template-columns:repeat(2,1fr); }
    .analytics-kpis { grid-template-columns:repeat(3,1fr); }
    .artist-grid, .genre-grid { grid-template-columns:repeat(3,1fr); }
    .song-row { grid-template-columns:minmax(160px,1fr) 130px 90px 80px; }
    .song-row > div:nth-child(5), .song-row > div:nth-child(6) { grid-column:auto; }
    .song-actions { grid-column:1/-1; justify-content:flex-start; }
  }

  @media (max-width:960px) {
    body { display:block; height:100vh; overflow:hidden; }
    .sidebar {
      position:fixed; inset:0 auto 0 0; z-index:100;
      width:min(80vw, 300px); min-width:0; max-width:300px;
      background:rgba(28,28,30,0.95);
      border-right:1px solid var(--separator);
      box-shadow:16px 0 48px rgba(0,0,0,0.4);
      backdrop-filter:blur(24px); -webkit-backdrop-filter:blur(24px);
      transform:translateX(-105%);
      transition:transform 280ms var(--ease-out);
    }
    .sidebar.open { transform:translateX(0); }
    .main { width:100%; height:100vh; min-width:0; }
    .topbar { min-height:var(--topbar-h); height:auto; padding:0 var(--sp-4); gap:var(--sp-2); }
    .mobile-menu-toggle { display:flex; }
    .topbar-title { font-size:17px; }
    .content { padding:var(--sp-4); gap:var(--sp-4); }
    .view-section { gap:var(--sp-4); }
    .two-col, .three-col, .section-grid, .upload-layout, .artist-form-layout,
    .settings-card-grid, .ad-status-layout, .ad-upload-layout { grid-template-columns:1fr; }
    .artist-grid, .genre-grid { grid-template-columns:repeat(2,1fr); }
    .ad-summary-grid { grid-template-columns:repeat(2,1fr); }
    .management-toolbar, .artist-toolbar, .analytics-filter-bar,
    .panel-header, .export-box, .sticky-save-bar { align-items:stretch; flex-direction:column; }
    .chart-summary { align-items:flex-start; }
    .chart-toggle { width:auto; justify-content:flex-start; flex-wrap:wrap; }
    .chart-toggle button { width:auto; flex:0 0 auto; }
    .chart-axis { padding-left:var(--sp-4); }
    .song-controls { display:grid; grid-template-columns:1fr; width:100%; }
    .song-search, .admin-control { width:100%; }

    .donut-wrap { grid-template-columns:1fr; justify-items:center; }
    .donut-legend { width:100%; }
    .line-chart-shell { min-height:240px; }
    .chart-visual { height:190px; }
    .notification-item { grid-template-columns:44px 1fr; }
    .dismiss-notification { grid-column:2; opacity:1; pointer-events:auto; justify-self:flex-start; }
  }

  @media (max-width:620px) {
    body { font-size:15px; }
    .content { padding:var(--sp-3); }
    .topbar { padding:0 var(--sp-3); }
    .topbar-sub { display:none; }
    .stats-grid, .analytics-kpis, .ad-summary-grid,
    .form-grid, .check-grid, .artist-grid, .genre-grid, .quick-grid { grid-template-columns:1fr 1fr; }
    .form-field.full { grid-column:1/-1; }
    .panel { border-radius:var(--radius-md); padding:var(--sp-4); }
    .stat-card { border-radius:var(--radius-md); }
    .stat-value { font-size:24px; }
    .song-row { grid-template-columns:1fr; gap:var(--sp-2); padding:var(--sp-3); }
    .song-title { white-space:normal; }
    .metric-badge, .status-pill, .artist-genre-pill { width:fit-content; }
    .song-actions { justify-content:flex-start; flex-wrap:wrap; }
    .edit-drawer { width:100vw; border-left:0; }
    .drawer-head, .drawer-body { padding:var(--sp-4); }
    .toast { right:var(--sp-3); left:var(--sp-3); bottom:var(--sp-4); text-align:center; border-radius:var(--radius-md); }
    .cover-upload-row, .settings-upload-row { grid-template-columns:1fr; }
  }

  @media (max-width:400px) {
    .stats-grid, .quick-grid { grid-template-columns:1fr 1fr; }
    .artist-grid, .genre-grid { grid-template-columns:1fr; }
  }

  /* ════════════════════════════════════════════
     APPLE HIG COLOR DISCIPLINE OVERRIDES
     Rule: Color = meaning, not decoration.
     • One tint (systemBlue) for interactive/primary
     • Semantic colors ONLY for status (green=ok, red=error, orange=warn)
     • All decorative multi-color removed → label hierarchy instead
     • Data viz: single tint + opacity variation
  ════════════════════════════════════════════ */

  /* CHART POINTS — tint only */
  .chart-point::before {
    background: var(--tint);
    box-shadow: 0 0 0 4px var(--tint-bg);
  }

  /* Legend dots use inline colors per genre */

  /* NOTIFICATION left borders — semantic ONLY (these carry real meaning) */
  /* download = info → tint */
  .notification-item.download { border-left-color: var(--tint); }
  /* ad = success → green (semantic) */
  .notification-item.ad { border-left-color: var(--sys-green); }
  /* warning → orange (semantic) */
  .notification-item.warning { border-left-color: var(--sys-orange); }
  /* error → red (semantic) */
  .notification-item.error { border-left-color: var(--sys-red); }
  /* system = neutral */
  .notification-item.system { border-left-color: var(--separator); }

  /* NOTIFICATION ICONS — neutral, not colored */
  .notification-icon {
    color: var(--label-secondary);
    font-size: 13px;
    font-weight: 700;
  }
  .notification-item.error .notification-icon { color: var(--sys-red); }
  .notification-item.warning .notification-icon { color: var(--sys-orange); }

  /* GENRE CARD accent strip — keep user-defined color (functional, identifies category) */
  /* But badge counts → neutral */
  .genre-counts .metric-badge { color: var(--label-secondary); }

  /* ACTIVITY DOTS — semantic only */
  .activity-dot { background: var(--label-quaternary); }
  /* green dot for actual success events is kept in HTML */

  /* AD STAT VALUES — neutral white */
  .ad-stat-val { color: var(--label); font-size: 14px; font-weight: 600; }

  /* STATUS PILLS — keep semantic (these communicate state) */
  /* published=green, draft=orange, unlisted=red — these are correct HIG usage */

  /* GENRE TAG / ARTIST PILL — neutral, not cyan */
  .genre-tag, .artist-genre-pill {
    background: var(--bg-tertiary);
    border: 1px solid var(--separator);
    color: var(--label-secondary);
    font-size: 11px;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: var(--radius-pill);
    white-space: nowrap;
    display: inline-flex;
    align-items: center;
  }

  /* SONG ACTION btn playing state */
  .song-action-btn.is-playing {
    border-color: var(--tint);
    color: var(--tint);
    background: var(--tint-bg);
  }

  /* ════════════════════════════════════════════
     SF SYMBOLS ICON SYSTEM
     • Consistent 18×18 rendered size
     • stroke-width 1.5 throughout
     • round linecap/linejoin (SF terminal style)
     • play.fill uses currentColor fill (SF convention)
     • Icons scale with font size via em units
  ════════════════════════════════════════════ */

  /* All SVG icons inside nav, buttons, icon-btns */
  .nav-item svg,
  .icon-btn svg,
  .btn svg,
  .quick-card svg {
    width: 18px;
    height: 18px;
    flex-shrink: 0;
    display: block;
  }

  /* Topbar export button icon */
  .topbar-right .btn svg {
    width: 16px;
    height: 16px;
  }

  /* SF Symbols: stroked icons — consistent weight */
  svg[stroke="currentColor"] {
    stroke-width: 1.5;
    stroke-linecap: round;
    stroke-linejoin: round;
  }

  /* SF play.fill — filled, no stroke */
  svg[fill="currentColor"] {
    stroke: none;
  }

  /* icon-btn — ensure icon is centered */
  .icon-btn {
    display: flex;
    align-items: center;
    justify-content: center;
  }

  /* Nav icon — slightly muted, inherits color on active */
  .nav-item svg {
    opacity: 0.65;
    transition: opacity var(--duration) var(--ease-out);
  }
  .nav-item:hover svg,
  .nav-item.active svg {
    opacity: 1;
  }


  /* ════════════════════════════════════
     ARTIST MANAGEMENT — clean redesign
     Matches HIG design system exactly
  ════════════════════════════════════ */

  /* Form layout: fields left, image right */
  .am-form-layout {
    display: grid;
    grid-template-columns: 1fr 220px;
    gap: var(--sp-5);
    align-items: start;
  }

  .am-fields { display:flex; flex-direction:column; }

  /* Image box */
  .am-image-box {
    background: var(--bg-tertiary);
    border: 1px solid var(--separator);
    border-radius: var(--radius-md);
    padding: var(--sp-4);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: var(--sp-3);
    position: relative; /* needed for absolute file input */
  }

  /* Avatar circle */
  .am-avatar {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: var(--bg-elevated);
    border: 1px solid var(--separator);
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: border-color var(--duration) var(--ease-out);
  }
  .am-avatar img {
    width: 100%; height: 100%;
    object-fit: cover; display: block;
  }

  /* Upload button */
  .am-upload-btn {
    width: 100%;
    min-height: 44px;
    background: var(--tint-bg);
    border: 1px dashed var(--tint-border);
    border-radius: var(--radius-sm);
    color: var(--tint);
    font-family: inherit;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: var(--sp-2);
    transition: background var(--duration) var(--ease-out),
                border-style var(--duration) var(--ease-out),
                transform 100ms var(--ease-out);
    -webkit-appearance: none;
    /* Only this button triggers file picker — nothing else */
    pointer-events: auto;
    position: relative;
    z-index: 1;
  }
  .am-upload-btn:hover { background: rgba(10,132,255,0.22); border-style: solid; }
  .am-upload-btn:active { transform: scale(0.98); }
  .am-upload-btn svg { flex-shrink:0; }

  /* Hint text */
  .am-upload-hint {
    font-size: 11px;
    color: var(--label-tertiary);
    text-align: center;
    line-height: 1.5;
  }

  /* Genre chips */
  .am-chips {
    display: flex;
    flex-wrap: wrap;
    gap: var(--sp-2);
    margin-top: var(--sp-2);
  }
  .am-chip {
    min-height: 32px;
    padding: 0 14px;
    border-radius: var(--radius-pill);
    border: 1px solid var(--separator);
    background: var(--bg-tertiary);
    color: var(--label-secondary);
    font-family: inherit;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    transition: all var(--duration) var(--ease-out);
    -webkit-appearance: none;
  }
  .am-chip:hover { background: var(--bg-elevated); color: var(--label); }
  .am-chip.active {
    background: var(--tint-bg);
    border-color: var(--tint-border);
    color: var(--tint);
    font-weight: 600;
  }

  /* Artist count badge */
  .am-count {
    display: inline-flex;
    align-items: center;
    background: var(--bg-tertiary);
    border: 1px solid var(--separator);
    color: var(--label-secondary);
    font-size: 11px;
    font-weight: 600;
    padding: 1px 8px;
    border-radius: var(--radius-pill);
    margin-left: var(--sp-2);
    vertical-align: middle;
  }

  /* Artist grid — override existing */
  #artistGrid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: var(--sp-3);
  }

  /* Empty state */
  .am-empty {
    min-height: 160px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border: 1px dashed var(--separator);
    border-radius: var(--radius-lg);
    padding: var(--sp-8);
    text-align: center;
  }

  /* Artist card — override existing for cleaner look */
  #artistGrid .artist-card {
    padding: var(--sp-5) var(--sp-3) var(--sp-4);
    border-radius: var(--radius-lg);
    min-height: unset;
    gap: var(--sp-3);
  }

  /* Artist avatar in card */
  #artistGrid .artist-avatar {
    width: 72px;
    height: 72px;
    margin-bottom: 0;
    border: 1px solid var(--separator);
    transition: border-color var(--duration) var(--ease-out);
  }
  #artistGrid .artist-card:hover .artist-avatar {
    border-color: var(--tint);
  }

  /* Card action buttons */
  #artistGrid .artist-card-actions {
    display: flex;
    gap: var(--sp-2);
    flex-wrap: wrap;
    justify-content: center;
  }

  /* Responsive */
  @media (max-width: 860px) {
    .am-form-layout { grid-template-columns: 1fr; }
    .am-image-box {
      flex-direction: row;
      align-items: center;
      gap: var(--sp-4);
    }
    .am-avatar { flex-shrink: 0; }
    .am-image-box > div:last-child { flex: 1; }
    .am-upload-hint { text-align: left; }
  }

  @media (max-width: 620px) {
    #artistGrid { grid-template-columns: repeat(2, 1fr); }
    .am-image-box { flex-direction: column; align-items: center; }
    /* Always show card actions on touch */
    #artistGrid .artist-card-actions { opacity: 1 !important; pointer-events: auto !important; }
  }


  /* Song metric cells — separate grid columns, never wrap */
  .song-metric-cell {
    display: flex;
    align-items: center;
  }
  .song-metric-cell .metric-badge {
    white-space: nowrap;
    font-size: 12px;
    font-weight: 600;
    padding: 5px 10px;
    color: var(--label-secondary);
    background: var(--bg-tertiary);
    border: 1px solid var(--separator);
    border-radius: var(--radius-pill);
  }

  /* Keep old class for any remaining uses */
  .song-row-metrics {
    display: flex;
    flex-wrap: wrap;
    gap: var(--sp-2);
    margin-top: 6px;
  }
  .song-row-metrics .metric-badge {
    font-size: 11px;
    font-weight: 600;
    padding: 3px 8px;
    white-space: nowrap;
    color: var(--label-secondary);
    background: var(--bg-tertiary);
    border: 1px solid var(--separator);
    border-radius: var(--radius-pill);
  }
  /* Override the global metric-badge for song rows specifically */
  .song-main .metric-badge {
    min-width: 0;
    white-space: nowrap;
  }


  /* ════════════════════════════════════════
     ADVERTISING SECTION — redesigned
  ════════════════════════════════════════ */

  /* Top row: current ad + performance side by side */
  .ad-top-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--sp-4);
  }

  /* Current ad layout: thumb | details | quick stats+toggle */
  .ad-current-layout {
    display: grid;
    grid-template-columns: 110px 1fr 220px;
    gap: var(--sp-5);
    align-items: start;
  }

  /* Portrait ad thumbnail (9:16) — fixed width, height driven by ratio */
  .ad-thumb-vertical {
    width: 110px;
    min-width: 110px;
    aspect-ratio: 9 / 16;
    border-radius: var(--radius-md);
    background: var(--bg-tertiary);
    border: 1px solid var(--separator);
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    flex-shrink: 0;
    align-self: start; /* don't stretch — let ratio control height */
  }
  .ad-thumb-vertical img {
    width: 100%; height: 100%;
    object-fit: cover; display: block;
  }
  .ad-thumb-label {
    position: absolute;
    bottom: 4px; right: 4px;
    background: rgba(0,0,0,0.55);
    color: var(--label-secondary);
    font-size: 9px;
    font-weight: 700;
    padding: 2px 5px;
    border-radius: 4px;
    letter-spacing: 0.04em;
  }

  /* Detail rows */
  .ad-current-details { display: flex; flex-direction: column; gap: var(--sp-2); }
  .ad-detail-row {
    display: flex;
    flex-direction: column;
    gap: 2px;
  }
  .ad-detail-key {
    font-size: 10px;
    font-weight: 700;
    color: var(--label-tertiary);
    text-transform: uppercase;
    letter-spacing: 0.07em;
  }
  .ad-detail-val {
    font-size: 13px;
    font-weight: 500;
    color: var(--label);
  }
  .ad-detail-url {
    color: var(--tint);
    font-size: 12px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 100%;
  }

  /* Performance mini cards */
  .ad-perf-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: var(--sp-3);
    margin-bottom: var(--sp-4);
  }
  .ad-perf-card {
    background: var(--bg-tertiary);
    border: 1px solid var(--separator);
    border-radius: var(--radius-md);
    padding: var(--sp-4);
    display: flex;
    flex-direction: column;
    gap: var(--sp-1);
  }
  .ad-perf-label {
    font-size: 10px;
    font-weight: 700;
    color: var(--label-tertiary);
    text-transform: uppercase;
    letter-spacing: 0.07em;
  }
  .ad-perf-value {
    font-size: 22px;
    font-weight: 700;
    letter-spacing: -0.03em;
    color: var(--label);
    line-height: 1.1;
  }
  .ad-perf-sub {
    font-size: 11px;
    color: var(--label-tertiary);
    margin-top: 2px;
  }

  /* Update form layout: fields + preview */
  .ad-update-layout {
    display: grid;
    grid-template-columns: 1fr 160px;
    gap: var(--sp-5);
    align-items: start;
  }
  .ad-update-fields { display: flex; flex-direction: column; gap: 0; }

  /* Upload button */
  .ad-upload-btn {
    width: 100%;
    min-height: 52px;
    background: var(--tint-bg);
    border: 1px dashed var(--tint-border);
    border-radius: var(--radius-md);
    color: var(--tint);
    font-family: inherit;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: var(--sp-2);
    transition: background var(--duration) var(--ease-out),
                border-style var(--duration) var(--ease-out),
                transform 100ms var(--ease-out);
    -webkit-appearance: none;
    margin-top: var(--sp-2);
  }
  .ad-upload-btn:hover { background: rgba(10,132,255,0.22); border-style: solid; }
  .ad-upload-btn:active { transform: scale(0.99); }

  /* File meta row */
  .ad-media-meta {
    display: none;
    align-items: center;
    gap: var(--sp-2);
    background: var(--tint-bg);
    border: 1px solid var(--tint-border);
    border-radius: var(--radius-sm);
    padding: var(--sp-2) var(--sp-3);
    font-size: 12px;
    font-weight: 500;
    color: var(--label-secondary);
    margin-top: var(--sp-2);
  }
  .ad-media-meta.is-visible { display: flex; }

  /* Ratio warning */
  .ratio-warning {
    display: none;
    background: var(--sys-orange-bg);
    border: 1px solid rgba(255,159,10,0.35);
    color: var(--sys-orange);
    border-radius: var(--radius-sm);
    padding: var(--sp-2) var(--sp-3);
    font-size: 12px;
    font-weight: 600;
    margin-top: var(--sp-2);
  }
  .ratio-warning.is-visible { display: block; }

  /* Toggles */
  .ad-toggles {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--sp-3);
    margin-top: var(--sp-4);
  }

  /* Right: preview frame (9:16 portrait) */
  .ad-update-preview {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: var(--sp-2);
  }
  .ad-preview-label {
    font-size: 11px;
    font-weight: 700;
    color: var(--label-tertiary);
    text-transform: uppercase;
    letter-spacing: 0.07em;
    align-self: flex-start;
  }
  .ad-preview-frame {
    width: 100%;
    aspect-ratio: 9 / 16;
    background: var(--bg-tertiary);
    border: 1px solid var(--separator);
    border-radius: var(--radius-md);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: var(--sp-2);
  }
  .ad-preview-frame img,
  .ad-preview-frame video {
    width: 100%; height: 100%;
    object-fit: cover; display: block;
  }
  .ad-preview-hint {
    font-size: 11px;
    color: var(--label-quaternary);
    text-align: center;
    padding: 0 var(--sp-2);
  }

  /* Responsive */
  @media (max-width: 960px) {
    .ad-top-grid { grid-template-columns: 1fr; }
    .ad-current-layout { grid-template-columns: 1fr; gap: var(--sp-4); }
    .ad-current-right { display: none; }
    .ad-thumb-vertical { width: 100%; max-width: 140px; min-width: auto; }
    .ad-perf-grid { grid-template-columns: repeat(2, 1fr); }
    .ad-update-layout { grid-template-columns: 1fr; }
    .ad-update-preview { flex-direction: row; align-items: flex-start; gap: var(--sp-4); }
    .ad-preview-frame { width: 100px; }
    .ad-toggles { grid-template-columns: 1fr; }
  }

  @media (max-width: 620px) {
    .ad-current-layout {
      grid-template-columns: 1fr;
      gap: var(--sp-3);
    }
    .ad-thumb-vertical {
      width: 80px;
      max-width: 80px;
      margin: 0 auto var(--sp-2);
    }
    .ad-perf-grid { grid-template-columns: 1fr 1fr; }
    .ad-perf-bar-row { grid-template-columns: 100px 1fr 40px; }
    .ad-toggles { grid-template-columns: 1fr; }
  }


  /* Ad performance progress bars */
  .ad-perf-bars {
    display: flex;
    flex-direction: column;
    gap: var(--sp-3);
    padding-top: var(--sp-4);
    border-top: 1px solid var(--separator);
  }
  .ad-perf-bar-row {
    display: grid;
    grid-template-columns: 150px 1fr 48px;
    gap: var(--sp-3);
    align-items: center;
  }
  .ad-perf-bar-label {
    font-size: 12px;
    font-weight: 500;
    color: var(--label-secondary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .ad-perf-bar-track {
    height: 6px;
    background: var(--bg-elevated);
    border-radius: var(--radius-pill);
    overflow: hidden;
  }
  .ad-perf-bar-fill {
    height: 100%;
    background: var(--tint);
    border-radius: var(--radius-pill);
  }
  .ad-perf-bar-val {
    font-size: 11px;
    font-weight: 600;
    color: var(--label-secondary);
    text-align: right;
    white-space: nowrap;
  }

  .ad-thumb-vertical {
    flex-shrink: 0;
  }

  /* Responsive fixes for new 2x2 grid */
  @media (max-width: 620px) {
    .ad-perf-grid { grid-template-columns: repeat(2, 1fr); }
    .ad-perf-bar-row { grid-template-columns: 110px 1fr 40px; }
  }


  /* Right column of Current Ad: quick stats + toggle */
  .ad-current-right {
    display: flex;
    flex-direction: column;
    gap: var(--sp-3);
    height: 100%;
  }
  .ad-quick-stats {
    display: flex;
    flex-direction: column;
    gap: var(--sp-2);
    background: var(--bg-tertiary);
    border: 1px solid var(--separator);
    border-radius: var(--radius-md);
    padding: var(--sp-4);
  }
  .ad-quick-stat {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: var(--sp-2);
    padding-bottom: var(--sp-2);
    border-bottom: 1px solid var(--separator);
  }
  .ad-quick-stat:last-child {
    border-bottom: none;
    padding-bottom: 0;
  }
  .ad-quick-val {
    font-size: 20px;
    font-weight: 700;
    letter-spacing: -0.03em;
    color: var(--label);
  }
  .ad-quick-label {
    font-size: 11px;
    font-weight: 600;
    color: var(--label-tertiary);
    text-transform: uppercase;
    letter-spacing: 0.06em;
  }

  /* Performance grid — 4 cols full width */
  .ad-perf-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: var(--sp-3);
    margin-bottom: var(--sp-4);
  }

  /* Responsive handled above */


  /* ════════════════════════════════════════
     APPLE HIG CALENDAR PICKER
     Matches iOS/macOS date picker spec:
     • Clean white-on-dark cells
     • Tint circle for selected day
     • Range highlight between start–end
     • Chevron navigation, month label
     • 44pt touch targets on day cells
  ════════════════════════════════════════ */

  .hig-calendar-wrap {
    padding: 0 0 var(--sp-2);
    animation: hig-fade-up 220ms var(--ease-out) both;
  }

  .hig-calendar {
    background: var(--bg-secondary);
    border: 1px solid var(--separator);
    border-radius: var(--radius-xl);
    overflow: hidden;
    max-width: 380px;
  }

  /* Header row: prev | Month Year | next */
  .hig-cal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: var(--sp-4) var(--sp-5);
    border-bottom: 1px solid var(--separator);
  }

  .hig-cal-month {
    font-size: 17px;
    font-weight: 600;
    color: var(--label);
    letter-spacing: -0.02em;
  }

  .hig-cal-nav {
    width: 36px;
    height: 36px;
    border: none;
    border-radius: 50%;
    background: var(--bg-tertiary);
    color: var(--tint);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background var(--duration) var(--ease-out);
  }
  .hig-cal-nav:hover { background: var(--bg-elevated); }
  .hig-cal-nav:active { transform: scale(0.93); }

  /* Day-of-week labels */
  .hig-cal-days-header {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    padding: var(--sp-3) var(--sp-4) var(--sp-2);
  }
  .hig-cal-days-header span {
    text-align: center;
    font-size: 12px;
    font-weight: 600;
    color: var(--label-tertiary);
    text-transform: uppercase;
    letter-spacing: 0.04em;
  }

  /* Day grid */
  .hig-cal-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    padding: 0 var(--sp-4) var(--sp-3);
    gap: 2px 0;
  }

  /* Each day cell */
  .hig-cal-day {
    position: relative;
    aspect-ratio: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 15px;
    font-weight: 400;
    color: var(--label);
    cursor: pointer;
    border: none;
    background: transparent;
    font-family: inherit;
    border-radius: 50%;
    transition: background var(--duration) var(--ease-out),
                color var(--duration) var(--ease-out);
    min-width: 0;
    z-index: 1;
  }
  .hig-cal-day:hover:not(.empty):not(.disabled) {
    background: var(--bg-tertiary);
  }
  .hig-cal-day.empty { pointer-events: none; }
  .hig-cal-day.disabled {
    color: var(--label-quaternary);
    pointer-events: none;
  }

  /* Today — tint label, no circle */
  .hig-cal-day.today {
    color: var(--tint);
    font-weight: 600;
  }

  /* Selected start or end — filled tint circle */
  .hig-cal-day.selected {
    background: var(--tint) !important;
    color: #fff !important;
    font-weight: 700;
    border-radius: 50%;
    z-index: 2;
  }

  /* In-range days — tint background strip */
  .hig-cal-day.in-range {
    background: var(--tint-bg);
    color: var(--label);
    border-radius: 0;
  }
  /* Start of range — round left cap */
  .hig-cal-day.range-start {
    border-radius: 50% 0 0 50%;
  }
  /* End of range — round right cap */
  .hig-cal-day.range-end {
    border-radius: 0 50% 50% 0;
  }
  /* Single selection (no range yet) */
  .hig-cal-day.range-start.range-end {
    border-radius: 50%;
  }

  /* Footer: selection label + buttons */
  .hig-cal-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--sp-4);
    padding: var(--sp-3) var(--sp-5) var(--sp-4);
    border-top: 1px solid var(--separator);
  }
  .hig-cal-selection-label {
    font-size: 13px;
    color: var(--label-secondary);
    font-weight: 500;
  }

  /* Toggle button label */
  #calendarToggleBtn {
    gap: var(--sp-2);
    font-weight: 500;
    color: var(--label-secondary);
  }
  #calendarToggleBtn.active {
    background: var(--tint-bg);
    border-color: var(--tint-border);
    color: var(--tint);
  }


  /* ── HIG COMPACT DATE RANGE + DROPDOWN CALENDAR ── */
  .custom-date-range { position: relative; }

  .hig-date-range {
    display: flex;
    align-items: center;
    gap: 4px;
    background: var(--bg-secondary);
    border: 1px solid var(--separator);
    border-radius: var(--radius-pill);
    padding: 3px 6px 3px 4px;
  }

  .hig-date-btn {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: var(--radius-pill);
    border: none;
    background: transparent;
    cursor: pointer;
    font-family: inherit;
    transition: background var(--duration) var(--ease-out);
  }
  .hig-date-btn:hover { background: var(--bg-tertiary); }
  .hig-date-btn.active {
    background: var(--tint-bg);
    border: 1px solid var(--tint-border);
  }
  .hig-date-label {
    font-size: 10px;
    font-weight: 700;
    color: var(--label-tertiary);
    text-transform: uppercase;
    letter-spacing: 0.07em;
  }
  .hig-date-val {
    font-size: 13px;
    font-weight: 500;
    color: var(--label);
    white-space: nowrap;
  }
  .hig-date-btn.active .hig-date-val { color: var(--tint); }

  .hig-date-sep {
    font-size: 12px;
    color: var(--label-tertiary);
    padding: 0 2px;
    pointer-events: none;
  }

  /* ── NSDatePicker-style compact calendar dropdown ── */

  .nsdp-wrap {
    position: absolute;
    top: calc(100% + 6px);
    right: 0;
    z-index: 60;
    width: 224px;
    background: var(--bg-secondary);
    border: 1px solid var(--separator);
    border-radius: var(--radius-md);
    box-shadow: 0 4px 24px rgba(0,0,0,0.5), 0 0 0 0.5px rgba(255,255,255,0.06);
    overflow: hidden;
    animation: hig-fade-up 160ms var(--ease-out) both;
    user-select: none;
  }

  /* Header: chevron · Month Year · chevron */
  .nsdp-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 8px 6px;
    border-bottom: 1px solid var(--separator);
    background: var(--bg-tertiary);
  }
  .nsdp-month-label {
    font-size: 12px;
    font-weight: 600;
    color: var(--label);
    letter-spacing: -0.01em;
    flex: 1;
    text-align: center;
  }
  .nsdp-nav {
    width: 24px;
    height: 24px;
    border: 1px solid var(--separator);
    border-radius: var(--radius-sm);
    background: var(--bg-secondary);
    color: var(--tint);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: background var(--duration) var(--ease-out);
  }
  .nsdp-nav:hover { background: var(--bg-elevated); }
  .nsdp-nav:active { transform: scale(0.9); }

  /* Day-of-week header row */
  .nsdp-dow-row {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    padding: 5px 6px 2px;
  }
  .nsdp-dow-row span {
    text-align: center;
    font-size: 10px;
    font-weight: 600;
    color: var(--label-tertiary);
  }

  /* Day grid */
  .nsdp-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    padding: 2px 6px 6px;
    gap: 1px;
  }

  /* Each day cell — 28×28, matches macOS NSDatePicker sizing */
  .hig-mini-day {
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 400;
    color: var(--label);
    border: none;
    background: transparent;
    font-family: inherit;
    border-radius: 50%;
    cursor: pointer;
    transition: background 120ms var(--ease-out), color 120ms var(--ease-out);
    position: relative;
    z-index: 1;
    margin: 0 auto;
  }
  .hig-mini-day:hover:not(.mini-empty):not(.mini-disabled) {
    background: var(--bg-elevated);
  }
  .hig-mini-day.mini-empty    { pointer-events: none; }
  .hig-mini-day.mini-disabled { color: var(--label-quaternary); pointer-events: none; }

  /* Today — tint numeral, bold */
  .hig-mini-day.mini-today {
    color: var(--tint);
    font-weight: 700;
  }

  /* Selected — solid tint circle, white numeral — NSDatePicker style */
  .hig-mini-day.mini-selected {
    background: var(--tint) !important;
    color: #ffffff !important;
    font-weight: 600;
    border-radius: 50% !important;
    z-index: 2;
  }

  /* In-range strip */
  .hig-mini-day.mini-in-range  { background: var(--tint-bg); border-radius: 0; }
  .hig-mini-day.mini-range-start { background: var(--tint-bg); border-radius: 50% 0 0 50%; }
  .hig-mini-day.mini-range-end   { background: var(--tint-bg); border-radius: 0 50% 50% 0; }
  .hig-mini-day.mini-range-start.mini-range-end { border-radius: 50%; }

  /* Selected overrides range bg */
  .hig-mini-day.mini-selected.mini-range-start,
  .hig-mini-day.mini-selected.mini-range-end {
    background: var(--tint) !important;
    border-radius: 50% !important;
  }

  /* Footer: mode label + Today button */
  .nsdp-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 5px 8px 7px;
    border-top: 1px solid var(--separator);
    background: var(--bg-tertiary);
  }
  .nsdp-mode-label {
    font-size: 10px;
    font-weight: 600;
    color: var(--tint);
    text-transform: uppercase;
    letter-spacing: 0.06em;
  }
  .nsdp-today-btn {
    font-size: 11px;
    font-weight: 600;
    color: var(--tint);
    border: none;
    background: none;
    cursor: pointer;
    font-family: inherit;
    padding: 2px 4px;
    border-radius: 4px;
    transition: background var(--duration) var(--ease-out);
  }
  .nsdp-today-btn:hover { background: var(--tint-bg); }


  /* ══════════════════════════════════════════
     APPLE HIG DATE RANGE PICKER
     Text inputs + calendar + Cancel/Apply
  ══════════════════════════════════════════ */

  /* Trigger button — compact pill */
  .nsdp-trigger {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    height: 32px;
    padding: 0 12px;
    background: var(--bg-secondary);
    border: 1px solid var(--separator);
    border-radius: var(--radius-pill);
    color: var(--label-secondary);
    font-family: inherit;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all var(--duration) var(--ease-out);
    white-space: nowrap;
  }
  .nsdp-trigger:hover { background: var(--bg-tertiary); color: var(--label); }
  .nsdp-trigger.open  { border-color: var(--tint-border); color: var(--tint); background: var(--tint-bg); }
  .nsdp-trigger svg   { flex-shrink: 0; }

  /* Popup container */
  .nsdp-popup {
    position: absolute;
    top: calc(100% + 6px);
    right: 0;
    z-index: 60;
    width: 280px;
    background: var(--bg-secondary);
    border: 1px solid var(--separator);
    border-radius: var(--radius-lg);
    box-shadow: 0 4px 16px rgba(0,0,0,0.35), 0 16px 48px rgba(0,0,0,0.4);
    overflow: hidden;
    animation: hig-fade-up 160ms var(--ease-out) both;
  }

  /* Manual text inputs row */
  .nsdp-inputs-row {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 12px 12px 8px;
    border-bottom: 1px solid var(--separator);
  }
  .nsdp-input-wrap { flex: 1; }
  .nsdp-text-input {
    width: 100%;
    height: 34px;
    background: var(--bg-tertiary);
    border: 1px solid var(--separator);
    border-radius: var(--radius-sm);
    color: var(--label);
    font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", sans-serif;
    font-size: 13px;
    font-weight: 500;
    font-variant-numeric: tabular-nums;
    text-align: center;
    outline: none;
    padding: 0 8px;
    transition: border-color var(--duration) var(--ease-out),
                box-shadow var(--duration) var(--ease-out);
    -webkit-appearance: none;
  }
  .nsdp-text-input::placeholder { color: var(--label-quaternary); font-size: 11px; }
  .nsdp-text-input:focus {
    border-color: var(--tint);
    box-shadow: 0 0 0 3px var(--tint-bg);
  }
  .nsdp-text-input.error {
    border-color: var(--sys-red);
    box-shadow: 0 0 0 3px var(--sys-red-bg);
  }
  .nsdp-input-sep {
    font-size: 14px;
    color: var(--label-tertiary);
    flex-shrink: 0;
  }

  /* Days selected summary */
  .nsdp-summary {
    padding: 5px 12px 4px;
    font-size: 11px;
    font-weight: 600;
    color: var(--tint);
    letter-spacing: 0.02em;
    border-bottom: 1px solid var(--separator);
  }

  /* Month nav header */
  .nsdp-cal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 10px 4px;
  }
  .nsdp-month-label {
    font-size: 13px;
    font-weight: 600;
    color: var(--label);
    letter-spacing: -0.01em;
  }
  .nsdp-nav {
    width: 24px;
    height: 24px;
    border: 1px solid var(--separator);
    border-radius: var(--radius-sm);
    background: var(--bg-tertiary);
    color: var(--tint);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: background var(--duration) var(--ease-out);
  }
  .nsdp-nav:hover  { background: var(--bg-elevated); }
  .nsdp-nav:active { transform: scale(0.88); }

  /* Day-of-week row */
  .nsdp-dow-row {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    padding: 2px 8px 1px;
  }
  .nsdp-dow-row span {
    text-align: center;
    font-size: 10px;
    font-weight: 600;
    color: var(--label-tertiary);
  }

  /* Day grid */
  .nsdp-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    padding: 2px 8px 6px;
    gap: 1px 0;
  }

  /* Day cell */
  .hig-mini-day {
    width: 34px;
    height: 34px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    font-weight: 400;
    color: var(--label);
    border: none;
    background: transparent;
    font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", sans-serif;
    border-radius: 50%;
    cursor: pointer;
    transition: background 100ms var(--ease-out), color 100ms var(--ease-out);
    position: relative;
    z-index: 1;
    margin: 0 auto;
  }
  .hig-mini-day:hover:not(.mini-empty):not(.mini-disabled) {
    background: var(--bg-elevated);
  }
  .hig-mini-day.mini-empty    { pointer-events: none; }
  .hig-mini-day.mini-disabled { color: var(--label-quaternary); pointer-events: none; }
  .hig-mini-day.mini-today    { color: var(--tint); font-weight: 700; }

  /* Selected — filled circle */
  .hig-mini-day.mini-selected {
    background: var(--tint) !important;
    color: #fff !important;
    font-weight: 600;
    border-radius: 50% !important;
    z-index: 2;
  }
  /* Range strip */
  .hig-mini-day.mini-in-range    { background: var(--tint-bg); border-radius: 0; }
  .hig-mini-day.mini-range-start { background: var(--tint-bg); border-radius: 50% 0 0 50%; }
  .hig-mini-day.mini-range-end   { background: var(--tint-bg); border-radius: 0 50% 50% 0; }
  .hig-mini-day.mini-range-start.mini-range-end { border-radius: 50%; }
  .hig-mini-day.mini-selected.mini-range-start,
  .hig-mini-day.mini-selected.mini-range-end {
    background: var(--tint) !important; border-radius: 50% !important;
  }

  /* Cancel / Apply footer */
  .nsdp-actions {
    display: flex;
    gap: 8px;
    padding: 8px 12px 12px;
    border-top: 1px solid var(--separator);
    background: rgba(255,255,255,0.02);
  }

  .nsdp-row-sep { display: none; } /* not used in new design */

  /* Live PHP compatibility aliases and states */
  :root {
    --bg: var(--bg-primary);
    --surface: var(--bg-secondary);
    --surface2: var(--bg-tertiary);
    --surface3: var(--bg-elevated);
    --border: var(--separator);
    --cyan: var(--tint);
    --cyan-dim: var(--tint-bg);
    --cyan-glow: rgba(10,132,255,0.35);
    --green: var(--sys-green);
    --green-dim: var(--sys-green-bg);
    --red: var(--sys-red);
    --red-dim: var(--sys-red-bg);
    --orange: var(--sys-orange);
    --orange-dim: var(--sys-orange-bg);
    --purple: var(--sys-purple);
    --text: var(--label);
    --text-dim: var(--label-secondary);
    --text-faint: var(--label-tertiary);
    --sidebar: var(--sidebar-w);
    --header: var(--topbar-h);
  }

  .notice {
    margin: 16px 24px 0;
    border: 1px solid var(--separator);
    border-radius: var(--radius-md);
    padding: 12px 14px;
    font-weight: 600;
    color: var(--label);
    background: var(--bg-secondary);
  }
  .notice.error { border-color: rgba(255,69,58,0.45); background: var(--sys-red-bg); color: var(--label); }
  .notice.success { border-color: rgba(46,189,107,0.45); background: var(--sys-green-bg); color: var(--label); }
  .form-field {
    color: var(--label-secondary);
    font-size: 13px;
    font-weight: 600;
    letter-spacing: 0.01em;
  }
  .login-shell {
    width: 100vw;
    min-height: 100vh;
    display: grid;
    place-items: center;
    padding: var(--sp-6);
    background: radial-gradient(circle at 20% 20%, rgba(10,132,255,0.16), transparent 34%), var(--bg-primary);
  }
  .login-card { width: min(620px, 100%); }
  .brand-avatar svg { width: 22px; height: 22px; fill: #fff; }
  .song-delete-warning form, .genre-confirm-actions form, .inline-form { display: inline-flex; margin: 0; }
  .admin-form input[type=file] { padding: 12px; background: var(--bg-tertiary); }
  .song-row.is-hidden { display: none !important; }
  .track-edit-panel { display: none; grid-column: 1 / -1; border-top: 1px solid var(--separator); padding-top: 16px; margin-top: 4px; }
  .song-row.editing .track-edit-panel { display: block; }
  .sidebar-logout-form { margin: 0; }
  .sidebar-logout {
    width: 100%;
    border: 0;
    background: transparent;
    font: inherit;
    text-align: left;
  }
  .topbar-right { display: none !important; }
  .status-pill.published { color: var(--sys-green); background: var(--sys-green-bg); }
  .status-pill.draft { color: var(--sys-orange); background: var(--sys-orange-bg); }
  .status-pill.unlisted { color: var(--sys-red); background: var(--sys-red-bg); }
    </style>
  </head>
  <body>
    <?php if (!$isAuthed): ?>
      <main class="login-shell">
        <section class="panel login-card">
          <div class="panel-header">
            <span class="panel-title">SG Production Admin</span>
            <a class="btn btn-outline" href="/">View Site</a>
          </div>
          <?php foreach ($errors as $message): ?><div class="notice error"><?= e($message) ?></div><?php endforeach; ?>
          <form class="admin-form" method="post">
            <input type="hidden" name="action" value="login">
            <label class="form-field full">Password<input type="password" name="password" required></label>
            <div class="form-actions"><button class="btn btn-primary" type="submit">Open Admin</button></div>
          </form>
        </section>
      </main>
    <?php else: ?>
      <aside class="sidebar">
        <div class="brand"><div class="brand-avatar">SG</div><div class="brand-text"><div class="name">SG Production</div><div class="role">Admin Studio</div></div></div>
        <nav>
          <div class="nav-section"><div class="nav-label">Overview</div><a class="nav-item active" href="#dashboard" data-section="dashboard"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7.5" height="7.5" rx="1.5"/><rect x="13.5" y="3" width="7.5" height="7.5" rx="1.5"/><rect x="3" y="13.5" width="7.5" height="7.5" rx="1.5"/><rect x="13.5" y="13.5" width="7.5" height="7.5" rx="1.5"/></svg>Dashboard</a><a class="nav-item" href="#analytics" data-section="analytics"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="2 12 5 12 7 4 9 20 11 12 13 16 15 10 17 12 22 12"/></svg>Analytics</a></div>
          <div class="nav-section"><div class="nav-label">Music</div><a class="nav-item" href="#upload" data-section="upload"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16V4m0 0L8 8m4-4 4 4"/><path d="M4 20h16"/></svg>Upload New Song</a><a class="nav-item" href="#songs" data-section="songs"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V6l11-2v12"/><circle cx="6" cy="18" r="2.5"/><circle cx="20" cy="16" r="2.5"/></svg>Uploaded Songs</a><a class="nav-item" href="#artists" data-section="artists"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M16 11c1.66 0 3-1.34 3-3s-1.34-3-3-3"/><path d="M19 17c0-1.86-1.34-3.4-3-3.86"/><circle cx="9" cy="8" r="3"/><path d="M3 20c0-3.31 2.69-6 6-6s6 2.69 6 6"/></svg>Artist Management</a><a class="nav-item" href="#genres" data-section="genres"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12.586 2.586A2 2 0 0 0 11.172 2H4a2 2 0 0 0-2 2v7.172a2 2 0 0 0 .586 1.414l8 8a2 2 0 0 0 2.828 0l7.172-7.172a2 2 0 0 0 0-2.828z"/><circle cx="7.5" cy="7.5" r="1"/></svg>Genre Management</a></div>
          <div class="nav-section"><div class="nav-label">Monetization</div><a class="nav-item" href="#advertising" data-section="advertising"><svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="2" stroke-miterlimit="10"><polygon points="6,19 3,19 3,13 6,13 29,6 29,26"/><path d="M15,21.8l-0.3,1c-0.5,1.7-2.3,2.6-3.9,2.1l0,0c-1.7-0.5-2.6-2.3-2.1-3.9L9,20"/></svg>Advertising</a></div>
          <div class="nav-section"><div class="nav-label">Site</div><a class="nav-item" href="#settings" data-section="settings"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M12 2v2M12 20v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M2 12h2M20 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>Website Settings</a><a class="nav-item notif" href="#notifications" data-section="notifications"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 6-3 8-3 8h18s-3-2-3-8"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>Notifications</a></div>
        </nav>
        <div class="sidebar-footer">
          <a class="nav-item" href="/"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>View Live Site</a>
          <form method="post" class="sidebar-logout-form"><input type="hidden" name="action" value="logout"><button class="nav-item sidebar-logout" type="submit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>Log Out</button></form>
        </div>
      </aside>
      <div class="sidebar-scrim" id="sidebarScrim"></div>
      <div class="main">
        <div class="topbar">
          <button class="mobile-menu-toggle" id="mobileMenuToggle" type="button" aria-label="Open admin menu" aria-expanded="false"><span></span><span></span><span></span></button>
          <div><span class="topbar-title">Dashboard</span><span class="topbar-sub">— <?= e(date('M Y')) ?></span></div>
          <div class="topbar-right"><button class="btn btn-ghost" type="button" onclick="alert('Report export will use real analytics once tracking is available.')">Export Report</button><button class="btn btn-primary" id="topPrimaryAction" data-action-section="upload" type="button">+ Upload Song</button></div>
        </div>
        <?php foreach ($errors as $message): ?><div class="notice error"><?= e($message) ?></div><?php endforeach; ?>
        <?php if ($success !== ''): ?><div class="notice success"><?= e($success) ?></div><?php endif; ?>
        <div class="content">
          <section class="view-section active" id="dashboard-section" data-title="Dashboard" data-subtitle="<?= e(date('M Y')) ?>">
            <div class="stats-grid">
              <div class="stat-card cyan"><div class="stat-icon"></div><div class="stat-label">Total Downloads</div><div class="stat-value cyan"><?= e($statText($totalDownloads,$hasDownloadData)) ?></div><div class="stat-change up">N/A vs last period</div></div>
              <div class="stat-card green"><div class="stat-icon"></div><div class="stat-label">Ad Impressions</div><div class="stat-value green"><?= e($statText($adImpressions, $hasAdData)) ?></div><div class="stat-change up">N/A vs last period</div></div>
              <div class="stat-card orange"><div class="stat-icon"></div><div class="stat-label">Ad Clicks (CTR)</div><div class="stat-value orange"><?= e($statText($adClicks, $hasAdData)) ?> <span style="font-size:16px;color:var(--text-dim)">/ <?= e($statSmallText($ctr, $hasAdData, '%')) ?></span></div><div class="stat-change up">N/A vs last period</div></div>
              <div class="stat-card purple"><div class="stat-icon"></div><div class="stat-label">Song Page Views</div><div class="stat-value purple">N/A</div><div class="stat-change down">N/A vs last period</div></div>
            </div>
            <div class="two-col">
              <div class="panel"><div class="panel-header"><span class="panel-title">Top Songs by Downloads</span><button class="panel-action" data-action-section="songs" type="button">See All →</button></div><table class="song-table"><thead><tr><th>#</th><th>Song</th><th>Downloads</th><th>Ad Clicks</th></tr></thead><tbody>
                <?php if ($topTracks === []): ?><tr><td class="mono" style="color:var(--cyan)">--</td><td>N/A</td><td>N/A</td><td>N/A</td></tr><?php else: ?>
                  <?php foreach ($topTracks as $index => $track): ?>
                    <?php $downloads = $downloadCountFor($track); $adTrackClicks = $adClickCountFor($track); ?>
                    <tr><td class="mono" style="color:<?= $index === 0 ? 'var(--cyan)' : 'var(--text-dim)' ?>"><?= e(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)) ?></td><td><div class="song-info"><div class="song-cover"><img src="<?= e((string) ($track['cover'] ?? 'assets/cover-1.jpg')) ?>" alt=""></div><div><div class="song-name"><?= e((string) ($track['title'] ?? 'N/A')) ?></div><div class="song-meta"><?= e((string) ($track['genre'] ?? 'N/A')) ?> · <?= e((string) ($track['duration'] ?? 'N/A')) ?></div></div></div></td><td><span class="mono" style="color:var(--green)"><?= e($hasDownloadData ? number_format($downloads) : 'N/A') ?></span></td><td><span class="mono" style="color:var(--orange)"><?= e($hasAdData ? number_format($adTrackClicks) : 'N/A') ?></span></td></tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody></table></div>
              <div class="panel"><div class="panel-header"><span class="panel-title">Ad Performance Breakdown</span><button class="panel-action" type="button" id="adPerformanceExportCsv">Export CSV →</button></div>
                <?php $adRows = [['Total Impressions','cyan',$statText($adImpressions,$hasAdData),$hasAdData ? 85 : 0],['Unique Viewers','green','N/A',0],['Total Ad Clicks','orange',$statText($adClicks,$hasAdData),$hasAdData ? 25 : 0],['Click-Through Rate','purple',$statSmallText($ctr,$hasAdData,'%'),$hasAdData ? 45 : 0],['Avg. Time-on-Ad-Page','red','N/A',0]]; ?>
                <?php foreach ($adRows as $row): ?><div class="ad-stat-row"><div class="ad-stat-label"><div class="dot" style="background:var(--<?= e($row[1]) ?>)"></div><?= e($row[0]) ?></div><div><div class="ad-stat-val" style="color:var(--<?= e($row[1]) ?>)"><?= e((string) $row[2]) ?></div><div class="prog-bar" style="width:120px"><div class="prog-fill" style="width:<?= e((string) $row[3]) ?>%;background:var(--<?= e($row[1]) ?>)"></div></div></div></div><?php endforeach; ?>
                <div style="margin-top:16px;padding:12px;background:var(--surface2);border-radius:8px;border:1px solid var(--border)"><div style="font-size:10px;color:var(--text-dim);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;font-weight:700;">Downloads Trend (7 days)</div><div class="chart-wrap"><div class="bar-chart"><?php foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $day): ?><div class="bar" style="height:10%"><span><?= e($day) ?></span></div><?php endforeach; ?></div></div></div>
              </div>
            </div>
            <div class="three-col"><div class="panel"><div class="panel-header"><span class="panel-title">Quick Actions</span></div><div class="quick-grid"><button class="quick-card" type="button" data-action-section="upload"><div class="icon"></div><div class="label">Upload Song</div></button><button class="quick-card" type="button" data-action-section="advertising"><div class="icon"></div><div class="label">Update Ad</div></button><button class="quick-card" type="button" data-action-section="songs"><div class="icon"></div><div class="label">Feature Song</div></button><button class="quick-card" type="button" onclick="alert('CSV export will use real analytics once available.')"><div class="icon"></div><div class="label">Export CSV</div></button><button class="quick-card" type="button" data-action-section="artists"><div class="icon"></div><div class="label">Add Artist</div></button><button class="quick-card" type="button" onclick="alert('No WAV link selected.')"><div class="icon"></div><div class="label">Copy WAV Link</div></button></div></div>
              <div class="panel"><div class="panel-header"><span class="panel-title">Recent Activity</span><button class="panel-action" type="button">Clear</button></div><div><div class="activity-item"><div class="activity-dot" style="background:var(--sys-green)"></div><div><div class="activity-text"><?= $latestTrack ? '<strong>' . e((string) ($latestTrack['title'] ?? 'N/A')) . '</strong> uploaded' : 'N/A' ?></div><div class="activity-time"><?= $latestTrack ? 'Latest catalog update' : 'No activity yet' ?></div></div></div><div class="activity-item"><div class="activity-dot" style="background:var(--label-tertiary)"></div><div><div class="activity-text">Total downloads: <strong><?= e($statText($totalDownloads,$hasDownloadData)) ?></strong></div><div class="activity-time">Live download stats</div></div></div><div class="activity-item"><div class="activity-dot" style="background:var(--label-tertiary)"></div><div><div class="activity-text">Ad clicks: <strong><?= e($statText($adClicks,$hasAdData)) ?></strong></div><div class="activity-time">Live ad stats</div></div></div><div class="activity-item"><div class="activity-dot" style="background:var(--label-tertiary)"></div><div><div class="activity-text">Artists in catalog: <strong><?= e((string) $artistCount) ?></strong></div><div class="activity-time">Current catalog</div></div></div><div class="activity-item"><div class="activity-dot" style="background:var(--label-tertiary)"></div><div><div class="activity-text">Website settings saved</div><div class="activity-time">N/A</div></div></div></div></div>
              <div class="panel"><div class="panel-header"><span class="panel-title">Advertiser Report</span></div><div style="font-size:11px;color:var(--text-dim);margin-bottom:14px;line-height:1.6;">Share this report with brands to show your ad performance. Includes impressions, clicks, CTR, and top songs.</div><div style="background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:12px;margin-bottom:12px;"><div style="font-size:10px;color:var(--text-faint);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;font-weight:700;">Report Preview</div><div style="font-size:11px;color:var(--text-dim);display:flex;flex-direction:column;gap:5px;"><div style="display:flex;justify-content:space-between"><span>Period</span><span class="mono" style="color:var(--text)">N/A</span></div><div style="display:flex;justify-content:space-between"><span>Impressions</span><span class="mono" style="color:var(--cyan)"><?= e($statText($adImpressions,$hasAdData)) ?></span></div><div style="display:flex;justify-content:space-between"><span>Clicks</span><span class="mono" style="color:var(--orange)"><?= e($statText($adClicks,$hasAdData)) ?></span></div><div style="display:flex;justify-content:space-between"><span>CTR</span><span class="mono" style="color:var(--green)"><?= e($statSmallText($ctr,$hasAdData,'%')) ?></span></div></div></div><div style="display:flex;flex-direction:column;gap:6px;"><button class="btn btn-primary" style="width:100%;text-align:center;padding:9px;" type="button" id="advertiserDownloadPdf">Download PDF Report</button><button class="btn btn-outline" style="width:100%;text-align:center;padding:9px;" type="button" id="advertiserExportCsv">Export as CSV</button><button class="btn btn-outline" style="width:100%;text-align:center;padding:9px;color:var(--cyan);border-color:var(--cyan-dim);" type="button">Copy Shareable Link</button></div></div></div>
          </section>

          <section class="view-section" id="analytics-section" data-title="Analytics" data-subtitle="Performance overview"><div class="management-toolbar"><div><h2>Analytics</h2><p>Measure downloads, song views, ads, and engagement</p></div><button class="btn btn-primary" type="button" id="analyticsExportCsv">Export CSV</button></div><div class="analytics-filter-bar"><div class="date-chip-group"><button class="date-chip active" type="button" data-analytics-range="7D">7D</button><button class="date-chip" type="button" data-analytics-range="30D">30D</button><button class="date-chip" type="button" data-analytics-range="90D">90D</button><button class="date-chip" type="button" data-analytics-range="all">All Time</button></div><div class="custom-date-range" aria-label="Custom analytics date range"><input id="analyticsStartDate" type="hidden" value="<?= e($weekAgoIso) ?>"><input id="analyticsEndDate" type="hidden" value="<?= e($todayIso) ?>"><button type="button" class="nsdp-trigger" id="nsdpTrigger"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" width="13" height="13"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg><span id="nsdpTriggerLabel"><?= e(date('M j', strtotime($weekAgoIso))) ?> – <?= e(date('M j', strtotime($todayIso))) ?></span></button><div class="nsdp-popup" id="nsdpPopup" style="display:none;"><div class="nsdp-inputs-row"><div class="nsdp-input-wrap"><input class="nsdp-text-input" id="nsdpInputFrom" type="text" placeholder="DD/MM/YYYY" value="<?= e(date('d/m/Y', strtotime($weekAgoIso))) ?>" aria-label="Start date" maxlength="10" oninput="nsdpParseInput('from',this.value)" onkeydown="if(event.key==='Enter')nsdpParseInput('from',this.value,true)"></div><span class="nsdp-input-sep">–</span><div class="nsdp-input-wrap"><input class="nsdp-text-input" id="nsdpInputTo" type="text" placeholder="DD/MM/YYYY" value="<?= e(date('d/m/Y', strtotime($todayIso))) ?>" aria-label="End date" maxlength="10" oninput="nsdpParseInput('to',this.value)" onkeydown="if(event.key==='Enter')nsdpParseInput('to',this.value,true)"></div></div><div class="nsdp-summary" id="nsdpSummary">7 days selected</div><div class="nsdp-cal-header"><button type="button" class="nsdp-nav" onclick="miniCalNav(-1)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" width="11" height="11"><polyline points="15 18 9 12 15 6"/></svg></button><span class="nsdp-month-label" id="miniMonthLabel"><?= e(date('F Y')) ?></span><button type="button" class="nsdp-nav" onclick="miniCalNav(1)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" width="11" height="11"><polyline points="9 18 15 12 9 6"/></svg></button></div><div class="nsdp-dow-row"><span>M</span><span>T</span><span>W</span><span>T</span><span>F</span><span>S</span><span>S</span></div><div class="nsdp-grid" id="miniCalGrid"></div><div class="nsdp-actions"><button type="button" class="btn btn-ghost" onclick="nsdpCancel()" style="min-height:36px;border-radius:var(--radius-md);font-size:13px;flex:1;">Cancel</button><button type="button" class="btn btn-primary" id="analyticsApplyRange" onclick="nsdpApply()" style="min-height:36px;border-radius:var(--radius-md);font-size:13px;flex:1;">Apply</button></div></div></div></div><div class="analytics-kpis"><div class="stat-card cyan"><div class="stat-label">Total Downloads</div><div class="stat-value cyan"><?= e($statText($totalDownloads,$hasDownloadData)) ?></div><div class="stat-change up">N/A vs previous period</div></div><div class="stat-card green"><div class="stat-label">Total Song Page Views</div><div class="stat-value green">N/A</div><div class="stat-change up">N/A vs previous period</div></div><div class="stat-card orange"><div class="stat-label">Ad Impressions</div><div class="stat-value orange"><?= e($statText($adImpressions,$hasAdData)) ?></div><div class="stat-change up">N/A vs previous period</div></div><div class="stat-card purple"><div class="stat-label">Ad Clicks</div><div class="stat-value purple"><?= e($statText($adClicks,$hasAdData)) ?></div><div class="stat-change up">N/A vs previous period</div></div><div class="stat-card cyan"><div class="stat-label">CTR Percentage</div><div class="stat-value cyan"><?= e($statSmallText($ctr,$hasAdData,'%')) ?></div><div class="stat-change down">N/A vs previous period</div></div><div class="stat-card green"><div class="stat-label">Avg Session Duration</div><div class="stat-value green">N/A</div><div class="stat-change up">N/A vs previous period</div></div></div><div class="panel"><div class="panel-header"><span class="panel-title">Downloads Over Time</span><div class="chart-toggle" id="analyticsChartToggle"><button class="active" type="button" data-chart="Downloads">Downloads</button><button type="button" data-chart="Page Views">Page Views</button><button type="button" data-chart="Ad Clicks">Ad Clicks</button></div></div><div class="line-chart-shell"><div class="chart-summary"><span><strong id="analyticsChartTotal">N/A</strong> total downloads</span><span id="analyticsChartPeak">Peak: N/A</span></div><div class="chart-visual"><div class="chart-y-axis" aria-hidden="true"><span style="top:11.5%">1,000</span><span style="top:32.7%">750</span><span style="top:53.8%">500</span><span style="top:75%">250</span><span style="top:91.5%">0</span></div><svg viewBox="0 0 900 260" preserveAspectRatio="none"><g stroke="rgba(255,255,255,.08)" stroke-width="1"><line x1="70" y1="30" x2="880" y2="30"></line><line x1="70" y1="85" x2="880" y2="85"></line><line x1="70" y1="140" x2="880" y2="140"></line><line x1="70" y1="195" x2="880" y2="195"></line><line x1="70" y1="238" x2="880" y2="238"></line></g><polyline id="analyticsLine" fill="none" stroke="#0a84ff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" points="80,238 194,238 308,238 422,238 536,238 650,238 764,238 875,238"></polyline></svg><div class="chart-point-layer" id="analyticsPointLayer"></div></div><div class="chart-axis"><span>Day 1</span><span>Day 2</span><span>Day 3</span><span>Day 4</span><span>Day 5</span><span>Day 6</span><span>Day 7</span><span>Today</span></div></div></div><div class="two-col"><div class="panel"><div class="panel-header"><span class="panel-title">Top 5 Songs by Downloads</span></div><div class="horizontal-bars"><?php if ($topTracks === []): ?><div class="hbar-row"><div class="hbar-label">N/A</div><div class="hbar-track"><div class="hbar-fill" style="width:0%"></div></div><div class="hbar-value">N/A</div></div><?php else: ?><?php foreach ($topTracks as $track): ?><div class="hbar-row"><div class="hbar-label"><?= e((string) ($track['title'] ?? 'N/A')) ?></div><div class="hbar-track"><div class="hbar-fill" style="width:<?= $hasDownloadData ? '40' : '0' ?>%"></div></div><div class="hbar-value"><?= e($hasDownloadData ? number_format($downloadCountFor($track)) : 'N/A') ?></div></div><?php endforeach; ?><?php endif; ?></div></div><div class="panel"><div class="panel-header"><span class="panel-title">Traffic by Genre</span></div><div class="donut-wrap"><div class="donut-chart"></div><div class="donut-legend"><?php $colors = ['cyan','green','orange','purple']; $i=0; foreach (array_slice($genreUsage,0,4,true) as $genreName=>$count): $percent = $genreTotal > 0 ? round(($count / $genreTotal) * 100) : null; ?><div class="legend-row"><span class="legend-label"><span class="legend-dot" style="background:var(--<?= e($colors[$i] ?? 'cyan') ?>)"></span><?= e((string) $genreName) ?></span><strong><?= e($percent !== null ? $percent . '%' : 'N/A') ?></strong></div><?php $i++; endforeach; ?></div></div></div></div><div class="panel"><div class="panel-header"><span class="panel-title">Per-Song Analytics</span></div><div class="analytics-table-wrap"><table class="analytics-table" id="analyticsTable"><thead><tr><th data-sort="index">#</th><th>Cover</th><th data-sort="title">Song Title</th><th data-sort="artist">Artist</th><th data-sort="genre">Genre</th><th data-sort="views">Page Views</th><th data-sort="downloads">Downloads</th><th data-sort="impressions">Ad Impressions</th><th data-sort="clicks">Ad Clicks</th><th data-sort="ctr">CTR</th><th data-sort="time">Avg Time on Page</th></tr></thead><tbody><?php if ($trackCount === 0): ?><tr data-index="0" data-title="N/A" data-artist="N/A" data-genre="N/A" data-views="0" data-downloads="0" data-impressions="0" data-clicks="0" data-ctr="0" data-time="0"><td>N/A</td><td>N/A</td><td>N/A</td><td>N/A</td><td>N/A</td><td>N/A</td><td>N/A</td><td>N/A</td><td>N/A</td><td>N/A</td><td>N/A</td></tr><?php else: ?><?php foreach ($tracks as $index => $track): if (!is_array($track)) continue; $trackId=(string)($track['id']??''); $songStats=is_array($adSongs[$trackId]??null)?$adSongs[$trackId]:[]; $rowImpressions=(int)($songStats['impressions']??0); $rowClicks=(int)($songStats['clicks']??0); $rowCtr=$rowImpressions>0?round(($rowClicks/$rowImpressions)*100,2):null; $rowDownloads=$downloadCountFor($track); ?><tr data-index="<?= e((string)($index+1)) ?>" data-title="<?= e((string)($track['title']??'N/A')) ?>" data-artist="<?= e((string)($track['artist']??'N/A')) ?>" data-genre="<?= e((string)($track['genre']??'N/A')) ?>" data-views="0" data-downloads="<?= e((string)$rowDownloads) ?>" data-impressions="<?= e((string)$rowImpressions) ?>" data-clicks="<?= e((string)$rowClicks) ?>" data-ctr="<?= e((string)($rowCtr??0)) ?>" data-time="0"><td><?= e(str_pad((string)($index+1),2,'0',STR_PAD_LEFT)) ?></td><td><div class="table-cover"><img src="<?= e((string)($track['cover']??'assets/cover-1.jpg')) ?>" alt=""></div></td><td><?= e((string)($track['title']??'N/A')) ?></td><td><?= e((string)($track['artist']??'N/A')) ?></td><td><?= e((string)($track['genre']??'N/A')) ?></td><td>N/A</td><td><?= e($hasDownloadData ? number_format($rowDownloads) : 'N/A') ?></td><td><?= e($hasAdData ? number_format($rowImpressions) : 'N/A') ?></td><td><?= e($hasAdData ? number_format($rowClicks) : 'N/A') ?></td><td><?= e($hasAdData && $rowCtr !== null ? $rowCtr . '%' : 'N/A') ?></td><td>N/A</td></tr><?php endforeach; ?><?php endif; ?></tbody></table></div><div class="pagination" id="analyticsPagination"></div></div></section>

          <section class="view-section" id="upload-section" data-title="Upload New Song" data-subtitle="Add the public preview file and the WAV download link separately"><div class="management-toolbar"><div><h2>Upload New Song</h2><p>Add the public preview file and the WAV download link separately</p></div></div><div class="upload-layout"><div class="panel upload-main-card"><div class="panel-header"><span class="panel-title">Song Details</span></div><form class="admin-form" id="uploadSongForm" method="post" enctype="multipart/form-data"><input type="hidden" name="action" value="upload"><div class="form-grid"><label class="form-field">Song Title<input type="text" name="title" placeholder="Nagin Theme" required></label><label class="form-field">Artist<input type="text" name="artist" value="SG Production"></label><label class="form-field">Artist Profile<select name="artistId"><?php foreach ($artists as $artistOption): if (!is_array($artistOption)) continue; ?><option value="<?= e((string) ($artistOption['id'] ?? '')) ?>"><?= e((string) ($artistOption['name'] ?? 'Artist')) ?></option><?php endforeach; ?></select></label><label class="form-field">Genre<select name="genre"><?php foreach ($genreNames as $genreName): ?><option><?= e($genreName) ?></option><?php endforeach; ?></select></label><label class="form-field full">Preview Song File<input id="uploadPreviewFile" type="file" name="audio" accept=".wav,.mp3,audio/wav,audio/mpeg" required><span class="form-help">Accepts MP3/WAV. Duration will be detected from this file.</span><div class="file-meta" id="previewFileMeta"><span id="previewFileName">No file selected</span><span id="previewFileSize">0 MB</span></div></label><label class="form-field">Duration<input id="uploadDurationInput" type="text" name="duration" placeholder="0:0"></label><label class="form-field">Wave Style<select name="wave"><option value="sine">Sine</option><option value="square">Square</option><option value="sawtooth">Sawtooth</option><option value="triangle">Triangle</option></select></label><label class="form-field"><span>BPM</span><input type="number" name="bpm" value="124" min="40" max="240"></label><label class="form-field full">WAV Download URL<input type="url" name="downloadUrl" placeholder="https://example.com/downloads/nagin-theme.wav" required><span class="form-help">Direct URL for the full-quality WAV download.</span></label><label class="form-field full">Cover Image<div class="cover-upload-row"><div class="cover-preview-thumb" id="uploadCoverPreview">Cover</div><div><input id="uploadCoverInput" type="file" name="cover" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required><span class="form-help">Image preview appears after selection.</span></div></div></label><label class="form-field full">Credit Text<textarea name="creditText" rows="3" placeholder="Optional credit text shown on song page."></textarea></label></div><div class="check-grid"><label class="check-card toggle-card"><span>Show in Latest Releases</span><input type="checkbox" name="isNew" checked></label><label class="check-card toggle-card"><span>Mark as Featured</span><input type="checkbox" name="isFeatured"></label></div><div class="upload-progress" id="uploadProgress"><div class="progress-head"><span>Uploading song</span><span id="uploadProgressValue">0%</span></div><div class="progress-track"><div class="progress-fill" id="uploadProgressFill"></div></div></div><div class="form-actions"><button class="btn btn-outline" type="button" id="saveDraftButton">Save as Draft</button><button class="btn btn-primary" type="submit">Upload Song</button></div></form></div><aside class="panel tips-card"><div class="panel-header"><span class="panel-title">Upload Tips</span></div><ul><li><strong>Cover image:</strong> recommended 1:1 ratio, minimum 500x500px.</li><li><strong>Preview file:</strong> MP3, maximum 10MB for fast public playback.</li><li><strong>WAV link:</strong> should be a direct downloadable URL.</li><li><strong>Wave style:</strong> affects how the waveform looks on the public song page.</li></ul></aside></div></section>

          <section class="view-section" id="songs-section" data-title="Uploaded Songs" data-subtitle="Manage and monitor all your tracks"><div class="management-toolbar"><div><h2>Uploaded Songs</h2><p>Manage and monitor all your tracks</p></div><div class="song-controls"><input class="admin-control song-search" id="songSearchInput" type="search" placeholder="Search songs"><select class="admin-control" id="songGenreFilter"><option value="all">All Genres</option><?php foreach ($genreNames as $genreName): ?><option><?= e($genreName) ?></option><?php endforeach; ?></select><select class="admin-control" id="songSortSelect"><option value="newest">Newest</option><option value="downloads">Most Downloaded</option><option value="az">A-Z</option></select></div></div><div class="empty-state <?= $trackCount === 0 ? 'is-visible' : '' ?>" id="songsEmptyState"><div><h3>No songs uploaded yet</h3><p>Upload your first song to start building the catalog.</p><button class="btn btn-primary" type="button" data-action-section="upload">Upload Your First Song</button></div></div><div class="songs-list" id="songsList" style="display:<?= $trackCount > 0 ? 'flex' : 'none' ?>">
            <?php foreach ($tracks as $track): if (!is_array($track)) continue; $trackId = (string) ($track['id'] ?? ''); $title = (string) ($track['title'] ?? 'N/A'); $genre = (string) ($track['genre'] ?? 'N/A'); $duration = (string) ($track['duration'] ?? 'N/A'); $artistName = (string) ($track['artist'] ?? 'SG Production'); $downloads = $downloadCountFor($track); $adTrackClicks = $adClickCountFor($track); $status = 'Published'; ?>
              <article class="song-row" id="track-<?= e($trackId) ?>" data-id="<?= e($trackId) ?>" data-title="<?= e($title) ?>" data-artist="<?= e($artistName) ?>" data-artist-id="<?= e((string) ($track['artistId'] ?? '')) ?>" data-genre="<?= e($genre) ?>" data-duration="<?= e($duration) ?>" data-download-url="<?= e((string) ($track['downloadUrl'] ?? '')) ?>" data-bpm="<?= e((string) ($track['bpm'] ?? 124)) ?>" data-wave="<?= e((string) ($track['wave'] ?? 'sine')) ?>" data-credit="<?= e((string) ($track['creditText'] ?? '')) ?>" data-downloads="<?= e((string) $downloads) ?>" data-clicks="<?= e((string) $adTrackClicks) ?>" data-featured="<?= !empty($track['isFeatured']) ? 'true' : 'false' ?>" data-new="<?= !empty($track['isNew']) ? 'true' : 'false' ?>" data-status="<?= e($status) ?>" data-cover="<?= e((string) ($track['cover'] ?? 'assets/cover-1.jpg')) ?>" data-date="<?= e((string) ($track['createdAt'] ?? '')) ?>"><div class="song-main"><div class="song-thumb"><img src="<?= e((string) ($track['cover'] ?? 'assets/cover-1.jpg')) ?>" alt="<?= e($title) ?> cover"></div><div><div class="song-title"><?= e($title) ?></div><div class="song-detail"><?= e($artistName) ?> · <?= e($genre) ?> · <?= e($duration) ?></div></div></div><div><span class="metric-badge cyan"><?= e($hasDownloadData ? number_format($downloads) : 'N/A') ?> downloads</span></div><div><span class="metric-badge orange"><?= e($hasAdData ? number_format($adTrackClicks) : 'N/A') ?> ad clicks</span></div><div class="song-flags"><?php if (!empty($track['isFeatured'])): ?><span class="artist-genre-pill">Featured</span><?php endif; ?></div><div><span class="status-pill published">Published</span></div><div class="song-actions"><button class="icon-btn song-action-btn" type="button" data-edit-song aria-label="Edit song"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4z"/></svg></button><a class="icon-btn song-action-btn" href="<?= e((string) ($track['previewUrl'] ?? '#')) ?>" aria-label="Play song"><svg viewBox="0 0 24 24" fill="currentColor" stroke="none"><path d="M8 5.14v14l11-7-11-7z"/></svg></a><button class="icon-btn song-action-btn danger" type="button" data-delete-song aria-label="Delete song"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg></button></div><div class="song-delete-warning"><span>Are you sure you want to delete <?= e($title) ?>? This cannot be undone.</span><div><button class="btn btn-outline" type="button" data-cancel-delete>Cancel</button><form method="post"><input type="hidden" name="action" value="delete_track"><input type="hidden" name="trackId" value="<?= e($trackId) ?>"><button class="btn btn-primary" type="submit">Confirm Delete</button></form></div></div><div class="track-edit-panel"><form class="admin-form" method="post" enctype="multipart/form-data"><input type="hidden" name="action" value="update_track"><input type="hidden" name="trackId" value="<?= e($trackId) ?>"><div class="form-grid"><label class="form-field">Song Title<input name="title" value="<?= e($title) ?>" required></label><label class="form-field">Artist<input name="artist" value="<?= e($artistName) ?>"></label><label class="form-field">Artist Profile<select name="artistId"><?php foreach ($artists as $artistOption): if (!is_array($artistOption)) continue; ?><option value="<?= e((string) ($artistOption['id'] ?? '')) ?>" <?= (string) ($artistOption['id'] ?? '') === (string) ($track['artistId'] ?? '') ? 'selected' : '' ?>><?= e((string) ($artistOption['name'] ?? 'Artist')) ?></option><?php endforeach; ?></select></label><label class="form-field">Genre<select name="genre"><?php foreach ($genreNames as $genreName): ?><option <?= $genreName === $genre ? 'selected' : '' ?>><?= e($genreName) ?></option><?php endforeach; ?></select></label><label class="form-field">Duration<input name="duration" value="<?= e($duration) ?>"></label><label class="form-field">BPM<input type="number" name="bpm" value="<?= e((string) ($track['bpm'] ?? 124)) ?>"></label><label class="form-field">Wave Style<select name="wave"><?php foreach (['sine'=>'Sine','square'=>'Square','sawtooth'=>'Sawtooth','triangle'=>'Triangle'] as $waveValue=>$waveLabel): ?><option value="<?= e($waveValue) ?>" <?= (string) ($track['wave'] ?? 'sine') === $waveValue ? 'selected' : '' ?>><?= e($waveLabel) ?></option><?php endforeach; ?></select></label><label class="form-field full">WAV Download URL<input type="url" name="downloadUrl" value="<?= e((string) ($track['downloadUrl'] ?? '')) ?>" required></label><label class="form-field">Replace Cover<input type="file" name="cover" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"></label><label class="form-field">Replace Preview<input type="file" name="audio" accept=".wav,.mp3,audio/wav,audio/mpeg"></label><label class="form-field full">Credit Text<textarea name="creditText" rows="3"><?= e((string) ($track['creditText'] ?? '')) ?></textarea></label></div><div class="check-grid"><label class="check-card toggle-card"><span>Show in Latest Releases</span><input type="checkbox" name="isNew" <?= !empty($track['isNew']) ? 'checked' : '' ?>></label><label class="check-card toggle-card"><span>Mark as Featured</span><input type="checkbox" name="isFeatured" <?= !empty($track['isFeatured']) ? 'checked' : '' ?>></label></div><div class="form-actions"><button class="btn btn-primary" type="submit">Save Changes</button></div></form></div></article>
            <?php endforeach; ?>
          </div><div class="pagination" id="songsPagination"></div></section>

          <section class="view-section" id="artists-section" data-title="Artist Management" data-subtitle="Add artist profiles for the website">
            <div class="artist-toolbar">
              <div>
                <h2>Artist Management</h2>
                <p>Add artist profiles for the website</p>
              </div>
              <button class="btn btn-primary" type="button" data-artist-focus>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" width="15" height="15"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Add New Artist
              </button>
            </div>

            <div class="panel" id="artistFormPanel">
              <div class="panel-header">
                <span class="panel-title" id="artistFormTitle">Add Artist</span>
                <button class="panel-action" type="button" data-clear-artist>Clear</button>
              </div>

              <form class="am-form-layout" id="artistForm" method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save_artist">

                <div class="am-fields">
                  <div class="form-field">
                    <label for="artistNameInput">Artist Name</label>
                    <input id="artistNameInput" type="text" name="artistName" placeholder="e.g. SG Production" autocomplete="off" spellcheck="false" required>
                  </div>

                  <div class="form-field" style="margin-top:var(--sp-4);">
                    <label for="artistBioInput">Bio</label>
                    <textarea id="artistBioInput" rows="3" placeholder="Short description about the artist..." style="resize:none;height:88px;line-height:1.5;"></textarea>
                  </div>

                  <div class="form-field" style="margin-top:var(--sp-4);">
                    <label>Genre</label>
                    <div class="am-chips">
                      <button type="button" class="am-chip active" onclick="this.classList.toggle('active')">Hindi</button>
                      <button type="button" class="am-chip" onclick="this.classList.toggle('active')">Marathi</button>
                      <button type="button" class="am-chip" onclick="this.classList.toggle('active')">Pop</button>
                      <button type="button" class="am-chip" onclick="this.classList.toggle('active')">Electronic</button>
                      <button type="button" class="am-chip" onclick="this.classList.toggle('active')">Classical</button>
                      <button type="button" class="am-chip" onclick="this.classList.toggle('active')">Jazz</button>
                    </div>
                  </div>

                  <div class="form-actions" style="margin-top:var(--sp-5);padding-top:var(--sp-4);border-top:1px solid var(--separator);">
                    <button class="btn btn-ghost" type="reset" data-clear-artist>Clear Form</button>
                    <button class="btn btn-primary" type="submit" style="flex:1;border-radius:var(--radius-md);">Save Artist</button>
                  </div>
                </div>

                <div class="am-image-box">
                  <div class="panel-title" style="margin-bottom:var(--sp-4);">Profile Image</div>

                  <div class="am-avatar" id="amAvatarWrap">
                    <img id="artistPreviewImage" data-artist-preview="new" src="assets/artist-photo-1.svg" alt="Artist profile preview">
                  </div>

                  <input id="artistImageInput" data-artist-image-input data-preview-target="new" data-file-name-target="new" type="file" name="artistImage" accept=".jpg,.jpeg,.png,.webp,.svg,image/jpeg,image/png,image/webp,image/svg+xml" tabindex="-1" aria-hidden="true" style="position:absolute;width:1px;height:1px;overflow:hidden;opacity:0;pointer-events:none;clip:rect(0,0,0,0);">

                  <button type="button" class="am-upload-btn" onclick="document.getElementById('artistImageInput').click(); event.stopPropagation();">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><path d="M12 16V4m0 0L8 8m4-4 4 4"/><path d="M4 20h16"/></svg>
                    Choose Image
                  </button>

                  <p class="am-upload-hint" id="artistImageFileName" data-file-name="new">No image selected.<br>Square image recommended.</p>
                </div>
              </form>
            </div>

            <div class="panel">
              <div class="panel-header">
                <span class="panel-title">Existing Artists <span class="am-count" id="artistCount"><?= e((string) $artistCount) ?></span></span>
              </div>

              <div class="am-empty" id="artistEmptyState" style="display:<?= $artistCount === 0 ? 'flex' : 'none' ?>;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" width="36" height="36" style="opacity:0.25;"><path d="M16 11c1.66 0 3-1.34 3-3s-1.34-3-3-3"/><path d="M19 17c0-1.86-1.34-3.4-3-3.86"/><circle cx="9" cy="8" r="3"/><path d="M3 20c0-3.31 2.69-6 6-6s6 2.69 6 6"/></svg>
                <p style="color:var(--label-tertiary);font-size:14px;margin-top:var(--sp-2);">No artists added yet</p>
                <button class="btn btn-primary" type="button" style="margin-top:var(--sp-3);" data-artist-focus>Add Your First Artist</button>
              </div>

              <div class="artist-grid" id="artistGrid" style="display:<?= $artistCount > 0 ? 'grid' : 'none' ?>">
                <?php foreach ($artists as $artist): if (!is_array($artist)) continue; $artistId=(string)($artist['id']??''); ?>
                  <article class="artist-card">
                    <div class="artist-avatar"><img src="<?= e((string) ($artist['image'] ?? 'assets/artist-photo-1.svg')) ?>" alt="<?= e((string) ($artist['name'] ?? 'Artist')) ?>"></div>
                    <div class="artist-name"><?= e((string) ($artist['name'] ?? 'Artist')) ?></div>
                    <div class="artist-card-actions">
                      <details class="editor">
                        <summary class="icon-btn" aria-label="Edit artist"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4z"/></svg></summary>
                        <form class="admin-form" method="post" enctype="multipart/form-data">
                          <input type="hidden" name="action" value="save_artist">
                          <input type="hidden" name="artistId" value="<?= e($artistId) ?>">
                          <label class="form-field">Artist Name<input type="text" name="artistName" value="<?= e((string) ($artist['name'] ?? '')) ?>" required></label>
                          <label class="form-field">Replace Image<input type="file" name="artistImage" accept=".jpg,.jpeg,.png,.webp,.svg,image/jpeg,image/png,image/webp,image/svg+xml"></label>
                          <button class="btn btn-primary" type="submit">Save</button>
                        </form>
                      </details>
                      <form method="post" onsubmit="return confirm('Delete this artist?');">
                        <input type="hidden" name="action" value="delete_artist">
                        <input type="hidden" name="artistId" value="<?= e($artistId) ?>">
                        <button class="icon-btn danger" type="submit" aria-label="Delete artist"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg></button>
                      </form>
                    </div>
                  </article>
                <?php endforeach; ?>
              </div>
            </div>
          </section>

          <section class="view-section" id="genres-section" data-title="Genre Management" data-subtitle="Add and manage genres for your songs and artists"><div class="management-toolbar"><div><h2>Genre Management</h2><p>Add and manage genres for your songs and artists</p></div></div><div class="panel" id="genreFormPanel"><div class="panel-header"><span class="panel-title">Add New Genre</span></div><form class="admin-form" id="genreForm" method="post"><input type="hidden" name="action" value="save_genre"><div class="form-grid"><label class="form-field">Genre Name<input id="genreNameInput" name="genreName" placeholder="Original Mix" required></label><label class="form-field">Genre Slug<input id="genreSlugInput" name="genreSlug" placeholder="original-mix"></label><label class="form-field full"><span class="char-row"><span>Genre Description</span><span class="char-count"><span id="genreDescriptionCount">0</span>/150</span></span><textarea id="genreDescriptionInput" name="genreDescription" maxlength="150" rows="3"></textarea></label><label class="form-field">Genre Color<input type="color" name="genreColor" value="#0a84ff"></label></div><div class="form-actions"><button class="btn btn-outline" type="reset" data-clear-genre>Clear</button><button class="btn btn-primary" id="genreSubmitButton" type="submit">Save Genre</button></div></form></div><div class="panel"><div class="panel-header"><span class="panel-title">Existing Genres</span><input class="admin-control" id="genreSearchInput" type="search" placeholder="Search genres"></div><div class="genre-grid" id="genreGrid"><?php foreach ($genres as $genre): if (!is_array($genre)) continue; $genreName=(string)($genre['name']??'Genre'); $counts=genreUsageCounts($genreName,$tracks,$artists); ?><article class="genre-card" style="--genre-color:<?= e((string)($genre['color']??'#0a84ff')) ?>" data-name="<?= e($genreName) ?>"><div class="genre-card-head"><div><div class="genre-card-title"><?= e($genreName) ?></div><div class="genre-slug"><?= e((string)($genre['slug']??'')) ?></div></div><div class="genre-card-actions"><details class="editor"><summary class="icon-btn" aria-label="Edit genre"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4z"/></svg></summary><form class="admin-form" method="post"><input type="hidden" name="action" value="save_genre"><input type="hidden" name="genreId" value="<?= e((string)($genre['id']??'')) ?>"><label class="form-field">Genre Name<input name="genreName" value="<?= e($genreName) ?>" required></label><label class="form-field">Genre Slug<input name="genreSlug" value="<?= e((string)($genre['slug']??'')) ?>"></label><label class="form-field">Color<input type="color" name="genreColor" value="<?= e((string)($genre['color']??'#0a84ff')) ?>"></label><label class="form-field full">Description<textarea name="genreDescription" rows="3"><?= e((string)($genre['description']??'')) ?></textarea></label><button class="btn btn-primary" type="submit">Update Genre</button></form></details><form method="post" onsubmit="return confirm('Deleting this genre will unassign it from all songs and artists. Continue?');"><input type="hidden" name="action" value="delete_genre"><input type="hidden" name="genreId" value="<?= e((string)($genre['id']??'')) ?>"><button class="icon-btn" type="submit" aria-label="Delete genre"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg></button></form></div></div><div class="genre-description"><?= e((string)($genre['description']??'')) ?></div><div class="genre-counts"><span class="metric-badge cyan"><?= e((string)$counts['songs']) ?> songs</span><span class="metric-badge orange"><?= e((string)$counts['artists']) ?> artists</span></div></article><?php endforeach; ?></div></div></section>

          <section class="view-section" id="advertising-section" data-title="Advertising" data-subtitle="Manage ads shown on song pages"><div class="management-toolbar"><div><h2>Advertising</h2><p>Manage ads shown on song pages</p></div></div><div class="panel"><div class="panel-header"><span class="panel-title">Current Ad Status</span><span class="status-pill <?= $adEnabled ? 'published' : 'unlisted' ?>" id="adStatusPill"><?= $adEnabled ? 'Active' : 'Inactive' ?></span></div><div class="ad-status-layout"><div class="ad-preview-thumb"><?php if ($adMediaUrl !== ''): ?><?php if ($adMediaType === 'video'): ?><video src="<?= e($adMediaUrl) ?>" muted loop playsinline></video><?php else: ?><img src="<?= e($adMediaUrl) ?>" alt="Current advertisement preview"><?php endif; ?><?php else: ?>N/A<?php endif; ?></div><div class="ad-detail-list"><div><strong>File:</strong> <?= e($adMediaUrl !== '' ? basename($adMediaUrl) : 'N/A') ?></div><div><strong>Type:</strong> <?= e($adMediaType !== '' ? $adMediaType : 'N/A') ?></div><div><strong>Click URL:</strong> <?= e($adLinkUrl !== '' ? $adLinkUrl : 'N/A') ?></div><div><strong>Last updated:</strong> N/A</div></div></div></div><div class="panel"><div class="panel-header"><span class="panel-title">Update Ad</span></div><form class="admin-form" id="adUpdateForm" method="post" enctype="multipart/form-data"><input type="hidden" name="action" value="save_ad"><div class="ad-update-layout"><div class="ad-update-fields"><label class="form-field">Advertising Media</label><input id="adMediaInput" class="ad-file-input" type="file" name="adMedia" accept=".jpg,.jpeg,.png,.webp,.mp4,.webm,.mov,image/jpeg,image/png,image/webp,video/mp4,video/webm"><button type="button" class="ad-upload-btn" id="adUploadButton">Choose media file</button><span class="form-help">Accepts JPG, PNG, WEBP, MP4, WEBM, or MOV. 9:16 ratio recommended.</span><div class="ad-media-meta" id="adMediaMeta"><span id="adFileName">No file selected</span><span id="adFileSize">0 MB</span><span id="adDimensions">Dimensions pending</span></div><div class="ratio-warning" id="adRatioWarning">For best results use 9:16 aspect ratio</div><label class="form-field">Advertisement Click URL<input id="adClickUrlInput" type="url" name="adLinkUrl" value="<?= e($adLinkUrl) ?>"></label><div class="ad-toggles"><label class="check-card toggle-card"><span>Show advertisement on single song pages</span><input id="sitewideAdToggle" type="checkbox" name="adEnabled" <?= $adEnabled ? 'checked' : '' ?>></label></div></div><div class="ad-update-preview"><div class="ad-preview-label">Preview</div><div class="ad-preview-frame" id="adSelectedPreview">Preview</div><div class="ad-preview-hint">Upload a portrait file to match song page ad display.</div></div></div><div class="panel-header" style="margin-top:24px"><span class="panel-title">Grid Ad (Music Library Card)</span><span class="status-pill <?= $gridAdEnabled ? 'published' : 'unlisted' ?>"><?= $gridAdEnabled ? 'Active' : 'Inactive' ?></span></div><p style="font-size:0.82rem;color:var(--label-secondary);margin:0 0 16px">Square 1:1 ad card inserted into the track grid on the homepage.</p><div class="form-grid"><div class="form-field full"><label style="display:block;margin-bottom:6px;font-weight:600">Ad Image (1:1 square)</label><?php if ($gridAdImageUrl !== ''): ?><img src="<?= e($gridAdImageUrl) ?>" alt="Grid ad preview" style="width:100px;height:100px;object-fit:cover;border-radius:8px;margin-bottom:8px;display:block"><?php endif; ?><input type="file" name="gridAdImage" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"><span class="form-help">JPG, PNG or WEBP. Use 1:1 ratio for best results.</span></div><label class="form-field">Advertiser Name<input type="text" name="gridAdName" value="<?= e($gridAdName) ?>" placeholder="e.g. Acme Corp"></label><label class="form-field">Subtext<input type="text" name="gridAdSubtext" value="<?= e($gridAdSubtext) ?>" placeholder="e.g. Premium Sponsor"></label><label class="form-field">Button Text<input type="text" name="gridAdButtonText" value="<?= e($gridAdButtonText) ?>" placeholder="Learn more"></label><label class="form-field">Button Background Color<input type="color" name="gridAdButtonColor" value="<?= e($gridAdButtonColor ?: '#ffffff') ?>"></label><label class="form-field">Button Text Color<input type="color" name="gridAdButtonTextColor" value="<?= e($gridAdButtonTextColor ?: '#000000') ?>"></label><label class="form-field full">Click URL<input type="url" name="gridAdLinkUrl" value="<?= e($gridAdLinkUrl) ?>" placeholder="https://advertiser.com"></label><label class="form-field">Grid Position (1 = first card)<input type="number" name="gridAdPosition" min="1" max="50" value="<?= e((string)$gridAdPosition) ?>"></label><div class="form-field full"><label class="check-card toggle-card"><span>Show grid ad on homepage</span><input type="checkbox" name="gridAdEnabled" <?= $gridAdEnabled ? 'checked' : '' ?>></label></div></div><div class="form-actions"><button class="btn btn-primary" type="submit">Save Advertising</button></div></form></div><div class="panel"><div class="panel-header"><span class="panel-title">Ad Performance Summary</span><button class="panel-action" type="button" data-action-section="analytics">View Full Ad Report →</button></div><div class="ad-summary-grid"><div class="stat-card cyan"><div class="stat-label">Total Impressions</div><div class="stat-value cyan"><?= e($statText($adImpressions,$hasAdData)) ?></div><div class="stat-change up">This month</div></div><div class="stat-card orange"><div class="stat-label">Total Clicks</div><div class="stat-value orange"><?= e($statText($adClicks,$hasAdData)) ?></div><div class="stat-change up">This month</div></div><div class="stat-card purple"><div class="stat-label">CTR</div><div class="stat-value purple"><?= e($statSmallText($ctr,$hasAdData,'%')) ?></div><div class="stat-change up">Campaign average</div></div><div class="stat-card green"><div class="stat-label">Top Song</div><div class="stat-value green" style="font-size:24px;">N/A</div><div class="stat-change up">N/A ad clicks</div></div></div></div><div class="panel"><div class="panel-header"><span class="panel-title">Ad History</span></div><div class="analytics-table-wrap"><table class="history-table"><thead><tr><th>Thumbnail</th><th>File Name</th><th>Period Active</th><th>Total Impressions</th><th>Total Clicks</th><th>CTR</th><th>Click URL</th></tr></thead><tbody><tr><td><?php if ($adMediaUrl !== ''): ?><div class="table-cover"><?php if ($adMediaType === 'video'): ?><video src="<?= e($adMediaUrl) ?>" muted loop playsinline></video><?php else: ?><img src="<?= e($adMediaUrl) ?>" alt=""><?php endif; ?></div><?php else: ?>N/A<?php endif; ?></td><td><?= e($adMediaUrl !== '' ? basename($adMediaUrl) : 'N/A') ?></td><td>N/A</td><td><?= e($statText($adImpressions,$hasAdData)) ?></td><td><?= e($statText($adClicks,$hasAdData)) ?></td><td><?= e($statSmallText($ctr,$hasAdData,'%')) ?></td><td><?= e($adLinkUrl !== '' ? $adLinkUrl : 'N/A') ?></td></tr></tbody></table></div></div></section>

          <section class="view-section" id="settings-section" data-title="Website Settings" data-subtitle="Site controls"><div class="management-toolbar"><div><h2>Website Settings</h2><p>Control public content, SEO, downloads, layout, and social links</p></div></div><form class="settings-stack" id="websiteSettingsForm" method="post" enctype="multipart/form-data"><input type="hidden" name="action" value="save_site"><div class="settings-card-grid"><div class="panel"><div class="panel-header"><span class="panel-title">Website Content</span></div><div class="admin-form"><label class="form-field">Website Title<input type="text" name="siteTitle" value="<?= e((string)($site['title']??'SG Production')) ?>"></label><label class="form-field">Tagline<input type="text" name="tagline" value="<?= e((string)($site['tagline']??'')) ?>"></label><label class="form-field">YouTube Subscribe Link<input type="url" name="youtube" value="<?= e((string)($links['youtube']??'')) ?>"></label></div></div><div class="panel"><div class="panel-header"><span class="panel-title">SEO</span></div><div class="admin-form"><label class="form-field">Default Page Title<input type="text" value="SG Production - Original Music Downloads" disabled></label><label class="form-field">Clean URL Format<input type="text" value="https://sgproduction.music/song-name" disabled></label><label class="form-field">Default Share Title<input type="text" value="Download music from SG Production" disabled></label></div></div></div><div class="panel"><div class="panel-header"><span class="panel-title">SEO & META</span></div><div class="form-grid"><label class="form-field full"><span class="char-row"><span>Meta Description</span><span class="char-count"><span id="metaDescriptionCount">0</span>/160</span></span><textarea id="metaDescriptionInput" name="metaDescription" rows="3" maxlength="160"><?= e((string)($seo['metaDescription']??'')) ?></textarea></label><label class="form-field">OG Image<input id="ogImageInput" type="file" name="ogImage" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"><span class="form-help">Current: <?= e((string)($seo['ogImage']??'N/A')) ?></span></label><label class="form-field">Favicon<input id="faviconInput" type="file" name="favicon" accept=".ico,.png,.svg,image/png,image/svg+xml"><span class="form-help">Current: <?= e((string)($seo['favicon']??'N/A')) ?></span></label><label class="form-field full">Google Analytics ID<input type="text" placeholder="G-XXXXXXXXXX" disabled></label></div></div><div class="panel"><div class="panel-header"><span class="panel-title">HOMEPAGE LAYOUT</span></div><div class="form-grid"><label class="form-field">Homepage Hero Text<input type="text" name="youtubeHeading" value="<?= e((string)($site['youtubeHeading']??'')) ?>"></label><label class="form-field">Homepage Sub-text<input type="text" name="youtubeText" value="<?= e((string)($site['youtubeText']??'')) ?>"></label><label class="form-field">Latest Count<input type="number" name="latestCount" min="0" max="12" value="<?= e((string)($catalog['latestCount']??5)) ?>"></label><label class="form-field">Songs Per Page<input type="number" name="tracksPerPage" min="5" max="50" value="<?= e((string)($catalog['tracksPerPage']??15)) ?>"></label><label class="form-field">Demo Page Count<input type="number" name="paginationDemoPages" min="1" max="40" value="<?= e((string)($catalog['paginationDemoPages']??12)) ?>"></label></div></div><div class="panel"><div class="panel-header"><span class="panel-title">SOCIAL LINKS</span></div><div class="form-grid"><label class="form-field">Instagram<input type="url" name="instagram" value="<?= e((string)($links['instagram']??'')) ?>"></label><label class="form-field">YouTube<input type="url" name="youtube" value="<?= e((string)($links['youtube']??'')) ?>"></label><label class="form-field">Spotify<input type="url" name="spotify" value="<?= e((string)($links['spotify']??'')) ?>"></label><label class="form-field">Apple Music<input type="url" name="appleMusic" value="<?= e((string)($links['appleMusic']??'')) ?>"></label><label class="form-field">Contact Email<input type="email" name="contactEmail" value="<?= e((string)($site['contactEmail']??'')) ?>"></label></div></div><div class="sticky-save-bar"><div><strong>Website Settings</strong><div class="save-copy">Save layout, SEO, download, and social changes together.</div></div><button class="btn btn-primary" type="submit">Save All Settings</button></div></form></section>

          <section class="view-section" id="notifications-section" data-title="Notifications" data-subtitle="Recent alerts"><div class="management-toolbar"><div><h2>Notifications</h2><p>Track downloads, ad activity, system alerts, and errors</p></div><button class="btn btn-primary" type="button" id="markAllReadButton">Mark All as Read</button></div><div class="notification-tabs"><button class="date-chip active" type="button" data-notification-filter="all">All</button><button class="date-chip" type="button" data-notification-filter="download">Downloads</button><button class="date-chip" type="button" data-notification-filter="ad">Ad Activity</button><button class="date-chip" type="button" data-notification-filter="system">System</button><button class="date-chip" type="button" data-notification-filter="error">Errors</button></div><div class="notification-feed" id="notificationFeed"><article class="notification-item system" data-type="system" data-unread="false"><div class="notification-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="M4.22 4.22l1.42 1.42"/><path d="M18.36 18.36l1.42 1.42"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="M4.22 19.78l1.42-1.42"/><path d="M18.36 5.64l1.42-1.42"/></svg></div><div><div class="notification-title-row"><span class="notification-title">N/A</span></div><div class="notification-description">No live notification feed is connected yet.</div><div class="notification-time">N/A</div></div><button class="btn btn-outline dismiss-notification" type="button">Dismiss</button></article></div><div class="panel notification-settings" id="notificationSettingsPanel"><div class="panel-header"><span class="panel-title">Notification Settings</span><button class="panel-action" type="button" id="toggleNotificationSettings">Show Settings</button></div><div class="notification-settings-body"><div class="check-grid"><label class="check-card toggle-card"><span>Email on every download</span><input type="checkbox"></label><label class="check-card toggle-card"><span>Email on ad clicks</span><input type="checkbox" checked></label><label class="check-card toggle-card"><span>Storage warnings</span><input type="checkbox" checked></label><label class="check-card toggle-card"><span>Broken link detection</span><input type="checkbox" checked></label></div></div></div></section>
        </div>
      </div>
      <div class="toast" id="settingsToast">Settings saved ✓</div>
      <script>
(() => {
  const sections = document.querySelectorAll('.view-section');
  const navItems = document.querySelectorAll('.nav-item[data-section]');
  const titleEl = document.querySelector('.topbar-title');
  const subEl = document.querySelector('.topbar-sub');
  const topPrimaryAction = document.querySelector('#topPrimaryAction');
  const sidebar = document.querySelector('.sidebar');
  const sidebarScrim = document.querySelector('#sidebarScrim');
  const mobileMenuToggle = document.querySelector('#mobileMenuToggle');
  const settingsToast = document.querySelector('#settingsToast');

  function showToast(message) {
    if (!settingsToast) return;
    settingsToast.textContent = message;
    settingsToast.classList.add('show');
    window.setTimeout(() => settingsToast.classList.remove('show'), 2200);
  }

  function normalizeSection(sectionName) {
    if (!sectionName || sectionName === 'top') return 'dashboard';
    const legacy = {
      'uploaded-songs': 'songs',
      'upload-song': 'upload',
      'global-settings': 'advertising',
      'website-settings': 'settings'
    };
    if (legacy[sectionName]) return legacy[sectionName];
    if (sectionName.startsWith('track-')) return 'songs';
    return sectionName;
  }

  function setMobileMenu(open) {
    sidebar?.classList.toggle('open', open);
    sidebarScrim?.classList.toggle('open', open);
    mobileMenuToggle?.setAttribute('aria-expanded', String(open));
  }

  function focusArtistForm() {
    const form = document.querySelector('#artistForm');
    const input = document.querySelector('#artistNameInput');
    form?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    window.setTimeout(() => input?.focus(), 180);
  }

  function focusGenreForm() {
    const panel = document.querySelector('#genreFormPanel');
    const input = document.querySelector('#genreNameInput');
    panel?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    window.setTimeout(() => input?.focus(), 180);
  }

  function openSection(rawSectionName) {
    const sectionName = normalizeSection(rawSectionName);
    const target = document.querySelector('#' + sectionName + '-section');
    if (!target) return;

    sections.forEach(section => section.classList.remove('active'));
    target.classList.add('active');
    navItems.forEach(item => item.classList.toggle('active', item.dataset.section === sectionName));
    if (titleEl) titleEl.textContent = target.dataset.title || 'Dashboard';
    if (subEl) subEl.textContent = '— ' + (target.dataset.subtitle || 'Admin Studio');
    if (topPrimaryAction) {
      const artistMode = sectionName === 'artists';
      const genreMode = sectionName === 'genres';
      topPrimaryAction.textContent = artistMode ? '+ Add New Artist' : genreMode ? '+ Add Genre' : '+ Upload Song';
      topPrimaryAction.dataset.actionSection = artistMode ? 'artists' : genreMode ? 'genres' : 'upload';
    }
    if (location.hash.replace('#', '') !== sectionName) history.replaceState(null, '', '#' + sectionName);
    setMobileMenu(false);
  }

  navItems.forEach(item => {
    item.addEventListener('click', event => {
      event.preventDefault();
      openSection(item.dataset.section);
    });
  });

  document.querySelectorAll('[data-action-section]').forEach(button => {
    button.addEventListener('click', () => {
      const section = button.dataset.actionSection;
      openSection(section);
      if (section === 'artists') focusArtistForm();
      if (section === 'genres') focusGenreForm();
    });
  });

  mobileMenuToggle?.addEventListener('click', () => setMobileMenu(!sidebar?.classList.contains('open')));
  sidebarScrim?.addEventListener('click', () => setMobileMenu(false));
  window.addEventListener('keydown', event => { if (event.key === 'Escape') setMobileMenu(false); });
  window.addEventListener('hashchange', () => openSection(location.hash.replace('#', '')));

  function formatFileSize(bytes) {
    if (!bytes) return '0 MB';
    const mb = bytes / 1024 / 1024;
    return mb.toFixed(mb >= 10 ? 0 : 1) + ' MB';
  }

  function formatDurationFromSeconds(seconds) {
    if (!Number.isFinite(seconds) || seconds <= 0) return '0:0';
    const minutes = Math.floor(seconds / 60);
    const remaining = Math.floor(seconds % 60);
    return minutes + ':' + remaining;
  }

  const uploadSongForm = document.querySelector('#uploadSongForm');
  const uploadPreviewFile = document.querySelector('#uploadPreviewFile');
  const previewFileMeta = document.querySelector('#previewFileMeta');
  const previewFileName = document.querySelector('#previewFileName');
  const previewFileSize = document.querySelector('#previewFileSize');
  const uploadDurationInput = document.querySelector('#uploadDurationInput');
  const uploadCoverInput = document.querySelector('#uploadCoverInput');
  const uploadCoverPreview = document.querySelector('#uploadCoverPreview');
  const uploadProgress = document.querySelector('#uploadProgress');
  const uploadProgressFill = document.querySelector('#uploadProgressFill');
  const uploadProgressValue = document.querySelector('#uploadProgressValue');
  const saveDraftButton = document.querySelector('#saveDraftButton');

  uploadPreviewFile?.addEventListener('change', () => {
    const file = uploadPreviewFile.files && uploadPreviewFile.files[0];
    if (!file) return;
    previewFileMeta?.classList.add('is-visible');
    if (previewFileName) previewFileName.textContent = file.name;
    if (previewFileSize) previewFileSize.textContent = formatFileSize(file.size);
    const audio = document.createElement('audio');
    const objectUrl = URL.createObjectURL(file);
    audio.preload = 'metadata';
    audio.src = objectUrl;
    audio.addEventListener('loadedmetadata', () => {
      if (uploadDurationInput) uploadDurationInput.value = formatDurationFromSeconds(audio.duration);
      URL.revokeObjectURL(objectUrl);
    });
    audio.addEventListener('error', () => URL.revokeObjectURL(objectUrl));
  });

  uploadCoverInput?.addEventListener('change', () => {
    const file = uploadCoverInput.files && uploadCoverInput.files[0];
    if (!file || !uploadCoverPreview) return;
    const image = document.createElement('img');
    image.src = URL.createObjectURL(file);
    image.alt = 'Selected cover preview';
    uploadCoverPreview.replaceChildren(image);
  });

  uploadSongForm?.addEventListener('submit', () => {
    uploadProgress?.classList.add('is-visible');
    if (uploadProgressFill) uploadProgressFill.style.width = '75%';
    if (uploadProgressValue) uploadProgressValue.textContent = '75%';
  });

  saveDraftButton?.addEventListener('click', () => showToast('Draft saving will be connected when draft status is enabled'));

  const artistForm = document.querySelector('#artistForm');
  const artistNameInput = document.querySelector('#artistNameInput');
  const artistPreviewImage = document.querySelector('#artistPreviewImage');
  const artistImageFileName = document.querySelector('#artistImageFileName');
  const artistGrid = document.querySelector('#artistGrid');
  const artistEmptyState = document.querySelector('#artistEmptyState');

  document.querySelectorAll('[data-artist-image-input]').forEach(input => {
    input.addEventListener('change', () => {
      const file = input.files && input.files[0];
      if (!file) return;
      const key = input.dataset.previewTarget || 'new';
      const preview = document.querySelector('[data-artist-preview="' + (window.CSS?.escape ? CSS.escape(key) : key) + '"]');
      const name = document.querySelector('[data-file-name="' + (window.CSS?.escape ? CSS.escape(input.dataset.fileNameTarget || 'new') : 'new') + '"]');
      const url = URL.createObjectURL(file);
      if (preview) preview.src = url;
      if (artistPreviewImage && key === 'new') artistPreviewImage.src = url;
      if (name) name.textContent = file.name;
      if (artistImageFileName && key === 'new') artistImageFileName.textContent = file.name;
    });
  });

  document.querySelectorAll('[data-clear-artist]').forEach(button => {
    button.addEventListener('click', () => {
      window.setTimeout(() => {
        if (artistPreviewImage) artistPreviewImage.src = 'assets/artist-photo-1.svg';
        if (artistImageFileName) artistImageFileName.innerHTML = 'No image selected.<br>Square image recommended.';
        artistNameInput?.focus();
      }, 0);
    });
  });
  document.querySelectorAll('[data-artist-focus]').forEach(button => button.addEventListener('click', focusArtistForm));
  if (artistGrid && artistEmptyState) {
    const hasArtists = artistGrid.querySelectorAll('.artist-card').length > 0;
    artistEmptyState.classList.toggle('is-visible', !hasArtists);
    artistGrid.style.display = hasArtists ? 'grid' : 'none';
  }

  const genreForm = document.querySelector('#genreForm');
  const genreNameInput = document.querySelector('#genreNameInput');
  const genreSlugInput = document.querySelector('#genreSlugInput');
  const genreDescriptionInput = document.querySelector('#genreDescriptionInput');
  const genreDescriptionCount = document.querySelector('#genreDescriptionCount');
  const genreSearchInput = document.querySelector('#genreSearchInput');
  const genreGrid = document.querySelector('#genreGrid');
  let genreSlugEdited = false;

  function slugify(value) {
    return String(value).toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
  }
  function updateGenreDescriptionCount() {
    if (genreDescriptionCount && genreDescriptionInput) genreDescriptionCount.textContent = String(genreDescriptionInput.value.length);
  }
  genreNameInput?.addEventListener('input', () => {
    if (!genreSlugEdited && genreSlugInput) genreSlugInput.value = slugify(genreNameInput.value);
  });
  genreSlugInput?.addEventListener('input', () => {
    genreSlugEdited = true;
    genreSlugInput.value = slugify(genreSlugInput.value);
  });
  genreDescriptionInput?.addEventListener('input', updateGenreDescriptionCount);
  genreForm?.addEventListener('reset', () => {
    genreSlugEdited = false;
    window.setTimeout(updateGenreDescriptionCount, 0);
  });
  genreSearchInput?.addEventListener('input', () => {
    const query = genreSearchInput.value.trim().toLowerCase();
    genreGrid?.querySelectorAll('.genre-card').forEach(card => {
      card.style.display = !query || (card.dataset.name || '').toLowerCase().includes(query) ? '' : 'none';
    });
  });
  updateGenreDescriptionCount();

  const adMediaInput = document.querySelector('#adMediaInput');
  const adUploadButton = document.querySelector('#adUploadButton');
  const adSelectedPreview = document.querySelector('#adSelectedPreview');
  const adMediaMeta = document.querySelector('#adMediaMeta');
  const adFileName = document.querySelector('#adFileName');
  const adFileSize = document.querySelector('#adFileSize');
  const adDimensions = document.querySelector('#adDimensions');
  const adRatioWarning = document.querySelector('#adRatioWarning');
  const sitewideAdToggle = document.querySelector('#sitewideAdToggle');
  const adStatusPill = document.querySelector('#adStatusPill');

  adUploadButton?.addEventListener('click', () => adMediaInput?.click());

  function checkAdRatio(width, height) {
    if (!width || !height) return;
    if (adDimensions) adDimensions.textContent = width + ' x ' + height;
    const ratio = width / height;
    const target = 9 / 16;
    adRatioWarning?.classList.toggle('is-visible', Math.abs(ratio - target) > 0.04);
  }

  adMediaInput?.addEventListener('change', () => {
    const file = adMediaInput.files && adMediaInput.files[0];
    if (!file || !adSelectedPreview) return;
    const objectUrl = URL.createObjectURL(file);
    adMediaMeta?.classList.add('is-visible');
    if (adFileName) adFileName.textContent = file.name;
    if (adFileSize) adFileSize.textContent = formatFileSize(file.size);
    if (adDimensions) adDimensions.textContent = 'Detecting dimensions...';
    if (file.type.startsWith('video/')) {
      const video = document.createElement('video');
      video.src = objectUrl;
      video.muted = true;
      video.loop = true;
      video.autoplay = true;
      video.playsInline = true;
      video.addEventListener('loadedmetadata', () => checkAdRatio(video.videoWidth, video.videoHeight));
      adSelectedPreview.replaceChildren(video);
      return;
    }
    const image = document.createElement('img');
    image.src = objectUrl;
    image.alt = 'Selected advertisement preview';
    image.addEventListener('load', () => checkAdRatio(image.naturalWidth, image.naturalHeight));
    adSelectedPreview.replaceChildren(image);
  });

  sitewideAdToggle?.addEventListener('change', () => {
    if (!adStatusPill) return;
    adStatusPill.textContent = sitewideAdToggle.checked ? 'Active' : 'Inactive';
    adStatusPill.className = 'status-pill ' + (sitewideAdToggle.checked ? 'published' : 'unlisted');
  });

  const metaDescriptionInput = document.querySelector('#metaDescriptionInput');
  const metaDescriptionCount = document.querySelector('#metaDescriptionCount');
  function updateMetaCounter() {
    if (metaDescriptionCount && metaDescriptionInput) metaDescriptionCount.textContent = String(metaDescriptionInput.value.length);
  }
  metaDescriptionInput?.addEventListener('input', updateMetaCounter);
  updateMetaCounter();

  const songsList = document.querySelector('#songsList');
  const songsEmptyState = document.querySelector('#songsEmptyState');
  const songsPagination = document.querySelector('#songsPagination');
  const songSearchInput = document.querySelector('#songSearchInput');
  const songGenreFilter = document.querySelector('#songGenreFilter');
  const songSortSelect = document.querySelector('#songSortSelect');
  let currentSongPage = 1;

  function getSongRows() { return Array.from(songsList?.querySelectorAll('.song-row') || []); }
  function getFilteredSongRows() {
    const query = (songSearchInput?.value || '').trim().toLowerCase();
    const genre = songGenreFilter?.value || 'all';
    const sort = songSortSelect?.value || 'newest';
    const rows = getSongRows().filter(row => {
      const haystack = ((row.dataset.title || '') + ' ' + (row.dataset.artist || '') + ' ' + (row.dataset.genre || '')).toLowerCase();
      return (!query || haystack.includes(query)) && (genre === 'all' || row.dataset.genre === genre);
    });
    rows.sort((a, b) => {
      if (sort === 'downloads') return Number(b.dataset.downloads || 0) - Number(a.dataset.downloads || 0);
      if (sort === 'az') return (a.dataset.title || '').localeCompare(b.dataset.title || '');
      return new Date(b.dataset.date || 0) - new Date(a.dataset.date || 0);
    });
    return rows;
  }
  function renderSongPagination(totalPages) {
    if (!songsPagination) return;
    songsPagination.innerHTML = '';
    if (totalPages <= 1) return;
    const makeButton = (label, active, disabled, onClick) => {
      const button = document.createElement('button');
      button.className = 'page-btn' + (active ? ' active' : '');
      button.textContent = label;
      button.disabled = disabled;
      button.addEventListener('click', onClick);
      songsPagination.appendChild(button);
    };
    makeButton('<', false, currentSongPage === 1, () => { currentSongPage = Math.max(1, currentSongPage - 1); renderSongs(); });
    for (let index = 1; index <= totalPages; index += 1) makeButton(String(index), index === currentSongPage, false, () => { currentSongPage = index; renderSongs(); });
    makeButton('>', false, currentSongPage === totalPages, () => { currentSongPage = Math.min(totalPages, currentSongPage + 1); renderSongs(); });
  }
  function renderSongs() {
    if (!songsList || !songsEmptyState) return;
    const filtered = getFilteredSongRows();
    const totalPages = Math.max(1, Math.ceil(filtered.length / 10));
    currentSongPage = Math.min(currentSongPage, totalPages);
    const visible = filtered.slice((currentSongPage - 1) * 10, currentSongPage * 10);
    getSongRows().forEach(row => { row.style.display = visible.includes(row) ? 'grid' : 'none'; });
    songsEmptyState.classList.toggle('is-visible', filtered.length === 0);
    songsList.style.display = filtered.length ? 'flex' : 'none';
    renderSongPagination(totalPages);
  }
  [songSearchInput, songGenreFilter, songSortSelect].forEach(control => {
    control?.addEventListener('input', () => { currentSongPage = 1; renderSongs(); });
    control?.addEventListener('change', () => { currentSongPage = 1; renderSongs(); });
  });
  songsList?.addEventListener('click', event => {
    const row = event.target.closest('.song-row');
    if (!row) return;
    if (event.target.closest('[data-edit-song]')) row.classList.toggle('editing');
    if (event.target.closest('[data-delete-song]')) {
      getSongRows().forEach(item => item.classList.remove('confirming'));
      row.classList.add('confirming');
    }
    if (event.target.closest('[data-cancel-delete]')) row.classList.remove('confirming');
  });
  renderSongs();

  const chartData = {
    Downloads: <?= json_encode($downloadChartData, JSON_UNESCAPED_SLASHES) ?>,
    'Page Views': { points: '80,238 194,238 308,238 422,238 536,238 650,238 764,238 875,238', values: ['N/A','N/A','N/A','N/A','N/A','N/A','N/A','N/A'], total: 'N/A', label: 'total page views', peak: 'Peak: N/A' },
    'Ad Clicks': { points: '80,238 194,238 308,238 422,238 536,238 650,238 764,238 875,238', values: ['N/A','N/A','N/A','N/A','N/A','N/A','N/A','N/A'], total: <?= json_encode($hasAdData ? number_format($adClicks) : 'N/A') ?>, label: 'total ad clicks', peak: 'Peak: N/A' }
  };
  function renderAnalyticsChart(type) {
    const data = chartData[type] || chartData.Downloads;
    const line = document.querySelector('#analyticsLine');
    const pointLayer = document.querySelector('#analyticsPointLayer');
    const total = document.querySelector('#analyticsChartTotal');
    const peak = document.querySelector('#analyticsChartPeak');
    const summaryLabel = total?.parentElement;
    line?.setAttribute('points', data.points);
    if (pointLayer) {
      pointLayer.innerHTML = data.points.split(' ').map((pair, index) => {
        const [x, y] = pair.split(',').map(Number);
        return '<button class="chart-point" type="button" style="left:' + ((x / 900) * 100) + '%;top:' + ((y / 260) * 100) + '%;" aria-label="' + data.values[index] + ' ' + data.label + '"><span class="chart-point-value">' + data.values[index] + '</span></button>';
      }).join('');
    }
    if (total) total.textContent = data.total;
    if (summaryLabel) summaryLabel.lastChild.textContent = ' ' + data.label;
    if (peak) peak.textContent = data.peak;
  }
  document.querySelectorAll('#analyticsChartToggle button').forEach(button => {
    button.addEventListener('click', () => {
      document.querySelectorAll('#analyticsChartToggle button').forEach(item => item.classList.remove('active'));
      button.classList.add('active');
      renderAnalyticsChart(button.dataset.chart);
    });
  });
  document.querySelectorAll('[data-analytics-range]').forEach(chip => {
    chip.addEventListener('click', () => {
      document.querySelectorAll('[data-analytics-range]').forEach(item => item.classList.remove('active'));
      chip.classList.add('active');
    });
  });

  const nsdpTrigger = document.querySelector('#nsdpTrigger');
  const nsdpPopup = document.querySelector('#nsdpPopup');
  const nsdpTriggerLabel = document.querySelector('#nsdpTriggerLabel');
  const nsdpSummary = document.querySelector('#nsdpSummary');
  const nsdpInputFrom = document.querySelector('#nsdpInputFrom');
  const nsdpInputTo = document.querySelector('#nsdpInputTo');
  const nsdpStart = document.querySelector('#analyticsStartDate');
  const nsdpEnd = document.querySelector('#analyticsEndDate');
  const miniMonthLabel = document.querySelector('#miniMonthLabel');
  const miniCalGrid = document.querySelector('#miniCalGrid');

  const parseIsoDate = value => {
    const parts = String(value || '').split('-').map(Number);
    return parts.length === 3 && parts.every(Number.isFinite) ? new Date(parts[0], parts[1] - 1, parts[2]) : new Date();
  };
  const parseDisplayDate = value => {
    const match = String(value || '').match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
    if (!match) return null;
    const date = new Date(Number(match[3]), Number(match[2]) - 1, Number(match[1]));
    return Number.isNaN(date.getTime()) ? null : date;
  };
  const isoDate = date => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
  const displayDate = date => `${String(date.getDate()).padStart(2, '0')}/${String(date.getMonth() + 1).padStart(2, '0')}/${date.getFullYear()}`;
  const shortDate = date => date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
  const monthLabel = date => date.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
  const dayKey = date => new Date(date.getFullYear(), date.getMonth(), date.getDate()).getTime();
  const nsdpState = {
    from: parseIsoDate(nsdpStart?.value),
    to: parseIsoDate(nsdpEnd?.value),
    view: new Date(parseIsoDate(nsdpStart?.value).getFullYear(), parseIsoDate(nsdpStart?.value).getMonth(), 1),
    previousFrom: parseIsoDate(nsdpStart?.value),
    previousTo: parseIsoDate(nsdpEnd?.value)
  };

  function updateNsdpLabels() {
    if (nsdpTriggerLabel) nsdpTriggerLabel.textContent = `${shortDate(nsdpState.from)} – ${shortDate(nsdpState.to)}`;
    if (nsdpSummary) {
      const days = Math.max(1, Math.round((dayKey(nsdpState.to) - dayKey(nsdpState.from)) / 86400000) + 1);
      nsdpSummary.textContent = `${days} day${days === 1 ? '' : 's'} selected`;
    }
    if (nsdpInputFrom) nsdpInputFrom.value = displayDate(nsdpState.from);
    if (nsdpInputTo) nsdpInputTo.value = displayDate(nsdpState.to);
  }

  function renderMiniCalendar() {
    if (!miniCalGrid || !miniMonthLabel) return;
    miniMonthLabel.textContent = monthLabel(nsdpState.view);
    const year = nsdpState.view.getFullYear();
    const month = nsdpState.view.getMonth();
    const first = new Date(year, month, 1);
    const offset = (first.getDay() + 6) % 7;
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const todayKey = dayKey(new Date());
    let html = '';
    for (let index = 0; index < offset; index += 1) html += '<button class="hig-mini-day mini-empty" tabindex="-1"></button>';
    for (let day = 1; day <= daysInMonth; day += 1) {
      const date = new Date(year, month, day);
      const key = dayKey(date);
      const start = dayKey(nsdpState.from);
      const end = dayKey(nsdpState.to);
      const classes = ['hig-mini-day'];
      if (key === todayKey) classes.push('mini-today');
      if (key >= start && key <= end) classes.push('mini-in-range');
      if (key === start) classes.push('mini-selected', 'mini-range-start');
      if (key === end) classes.push('mini-selected', 'mini-range-end');
      html += `<button class="${classes.join(' ')}" type="button" data-date="${isoDate(date)}">${day}</button>`;
    }
    miniCalGrid.innerHTML = html;
    miniCalGrid.querySelectorAll('[data-date]').forEach(button => {
      button.addEventListener('click', () => {
        const selected = parseIsoDate(button.dataset.date);
        if (dayKey(selected) < dayKey(nsdpState.from) || dayKey(nsdpState.from) === dayKey(nsdpState.to)) {
          nsdpState.from = selected;
          nsdpState.to = selected;
        } else {
          nsdpState.to = selected;
        }
        updateNsdpLabels();
        renderMiniCalendar();
      });
    });
  }


  window.nsdpParseInput = function (mode, value, apply = false) {
    const parsed = parseDisplayDate(value);
    const input = mode === 'from' ? nsdpInputFrom : nsdpInputTo;
    input?.classList.toggle('error', !parsed);
    if (!parsed) return;
    if (mode === 'from') nsdpState.from = parsed;
    if (mode === 'to') nsdpState.to = parsed;
    if (dayKey(nsdpState.from) > dayKey(nsdpState.to)) {
      const temp = nsdpState.from;
      nsdpState.from = nsdpState.to;
      nsdpState.to = temp;
    }
    nsdpState.view = new Date(nsdpState.from.getFullYear(), nsdpState.from.getMonth(), 1);
    updateNsdpLabels();
    renderMiniCalendar();
    if (apply) window.nsdpApply();
  };
  window.miniCalNav = function (direction) {
    nsdpState.view = new Date(nsdpState.view.getFullYear(), nsdpState.view.getMonth() + direction, 1);
    renderMiniCalendar();
  };
  window.nsdpCancel = function () {
    nsdpState.from = new Date(nsdpState.previousFrom);
    nsdpState.to = new Date(nsdpState.previousTo);
    updateNsdpLabels();
    renderMiniCalendar();
    nsdpPopup && (nsdpPopup.style.display = 'none');
    nsdpTrigger?.classList.remove('open');
  };
  window.nsdpApply = function () {
    if (dayKey(nsdpState.from) > dayKey(nsdpState.to)) return showToast('Start date must be before end date');
    nsdpState.previousFrom = new Date(nsdpState.from);
    nsdpState.previousTo = new Date(nsdpState.to);
    if (nsdpStart) nsdpStart.value = isoDate(nsdpState.from);
    if (nsdpEnd) nsdpEnd.value = isoDate(nsdpState.to);
    updateNsdpLabels();
    nsdpPopup && (nsdpPopup.style.display = 'none');
    nsdpTrigger?.classList.remove('open');
    showToast('Analytics range applied: ' + nsdpStart?.value + ' to ' + nsdpEnd?.value);
  };
  nsdpTrigger?.addEventListener('click', event => {
    event.stopPropagation();
    if (!nsdpPopup) return;
    const open = nsdpPopup.style.display !== 'none';
    nsdpPopup.style.display = open ? 'none' : 'block';
    nsdpTrigger.classList.toggle('open', !open);
    if (!open) renderMiniCalendar();
  });
  nsdpPopup?.addEventListener('click', event => event.stopPropagation());
  document.addEventListener('click', () => {
    nsdpPopup && (nsdpPopup.style.display = 'none');
    nsdpTrigger?.classList.remove('open');
  });
  // Ensure Export CSV button sits after the analytics filter bar
  (function(){
    const moveExportBtn = () => {
      const exportBtn = document.getElementById('analyticsExportCsv');
      const analyticsSection = document.getElementById('analytics-section');
      const filterBar = analyticsSection?.querySelector('.analytics-filter-bar');
      if (exportBtn && filterBar && filterBar.parentNode) {
        filterBar.parentNode.insertBefore(exportBtn, filterBar.nextSibling);
      }
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', moveExportBtn); else moveExportBtn();
  })();
  updateNsdpLabels();
  renderMiniCalendar();

  renderAnalyticsChart('Downloads');

  const analyticsTable = document.querySelector('#analyticsTable');
  const analyticsPagination = document.querySelector('#analyticsPagination');
  let analyticsPage = 1;
  let analyticsSortKey = 'index';
  let analyticsSortDir = 1;
  function getAnalyticsRows() { return Array.from(analyticsTable?.querySelectorAll('tbody tr') || []); }
  function renderAnalyticsTable() {
    if (!analyticsTable || !analyticsPagination) return;
    const tbody = analyticsTable.querySelector('tbody');
    const rows = getAnalyticsRows();
    rows.sort((a, b) => {
      const aValue = a.dataset[analyticsSortKey] || '';
      const bValue = b.dataset[analyticsSortKey] || '';
      const numeric = !Number.isNaN(Number(aValue)) && !Number.isNaN(Number(bValue));
      return numeric ? (Number(aValue) - Number(bValue)) * analyticsSortDir : aValue.localeCompare(bValue) * analyticsSortDir;
    });
    rows.forEach(row => tbody.appendChild(row));
    const totalPages = Math.max(1, Math.ceil(rows.length / 10));
    analyticsPage = Math.min(analyticsPage, totalPages);
    rows.forEach((row, index) => { row.style.display = index >= (analyticsPage - 1) * 10 && index < analyticsPage * 10 ? 'table-row' : 'none'; });
    analyticsPagination.innerHTML = '';
    if (totalPages <= 1) return;
    for (let index = 1; index <= totalPages; index += 1) {
      const button = document.createElement('button');
      button.className = 'page-btn' + (index === analyticsPage ? ' active' : '');
      button.textContent = String(index);
      button.addEventListener('click', () => { analyticsPage = index; renderAnalyticsTable(); });
      analyticsPagination.appendChild(button);
    }
  }
  analyticsTable?.querySelectorAll('th[data-sort]').forEach(header => {
    header.addEventListener('click', () => {
      const key = header.dataset.sort;
      analyticsSortDir = analyticsSortKey === key ? analyticsSortDir * -1 : 1;
      analyticsSortKey = key;
      analyticsPage = 1;
      renderAnalyticsTable();
    });
  });
  document.querySelectorAll('#adPerformanceExportCsv, #advertiserDownloadPdf, #advertiserExportCsv, #analyticsExportCsv').forEach(button => {
    button.addEventListener('click', () => {
      alert('Export preview. This will connect to real data when reporting is available.');
    });
  });
  renderAnalyticsTable();

  const notificationFeed = document.querySelector('#notificationFeed');

  function renderNotificationIcons() {
    const icons = {
      download: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>',
      ad: '<svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="2" stroke-miterlimit="10"><polygon points="6,19 3,19 3,13 6,13 29,6 29,26"/><path d="M15,21.8l-0.3,1c-0.5,1.7-2.3,2.6-3.9,2.1l0,0c-1.7-0.5-2.6-2.3-2.1-3.9L9,20"/></svg>',
      system: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="M4.22 4.22l1.42 1.42"/><path d="M18.36 18.36l1.42 1.42"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="M4.22 19.78l1.42-1.42"/><path d="M18.36 5.64l1.42-1.42"/></svg>',
      error: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>'
    };
    notificationFeed?.querySelectorAll('.notification-item').forEach(item => {
      const icon = item.querySelector('.notification-icon');
      const type = item.dataset.type;
      if (icon && icons[type]) icon.innerHTML = icons[type];
    });
  }

  renderNotificationIcons();

  document.querySelectorAll('[data-notification-filter]').forEach(tab => {
    tab.addEventListener('click', () => {
      document.querySelectorAll('[data-notification-filter]').forEach(item => item.classList.remove('active'));
      tab.classList.add('active');
      const filter = tab.dataset.notificationFilter;
      notificationFeed?.querySelectorAll('.notification-item').forEach(item => {
        item.style.display = filter === 'all' || item.dataset.type === filter ? 'grid' : 'none';
      });
    });
  });
  document.querySelector('#markAllReadButton')?.addEventListener('click', () => {
    notificationFeed?.querySelectorAll('.notification-item').forEach(item => {
      item.dataset.unread = 'false';
      item.querySelector('.unread-dot')?.remove();
    });
  });
  notificationFeed?.addEventListener('click', event => {
    const dismiss = event.target.closest('.dismiss-notification');
    if (dismiss) dismiss.closest('.notification-item')?.remove();
  });
  const toggleNotificationSettings = document.querySelector('#toggleNotificationSettings');
  const notificationSettingsPanel = document.querySelector('#notificationSettingsPanel');
  toggleNotificationSettings?.addEventListener('click', () => {
    notificationSettingsPanel?.classList.toggle('open');
    toggleNotificationSettings.textContent = notificationSettingsPanel?.classList.contains('open') ? 'Hide Settings' : 'Show Settings';
  });

  openSection(location.hash.replace('#', '') || 'dashboard');
})();
</script>
    <?php endif; ?>
  </body>
</html>
