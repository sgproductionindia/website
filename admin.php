<?php
declare(strict_types=1);

session_start();

const ADMIN_PASSWORD = 'hyqhyp-viKfa3-timfaw';
const TRACKS_FILE = __DIR__ . '/data/tracks.json';
const SETTINGS_FILE = __DIR__ . '/data/settings.json';
const ARTISTS_FILE = __DIR__ . '/data/artists.json';
const GENRES_FILE = __DIR__ . '/data/genres.json';
const AD_STATS_FILE = __DIR__ . '/data/ad-stats.json';
const COVER_DIR = __DIR__ . '/uploads/covers';
const AUDIO_DIR = __DIR__ . '/uploads/audio';
const AD_DIR = __DIR__ . '/uploads/ads';
const ARTIST_DIR = __DIR__ . '/uploads/artists';
const SITE_MEDIA_DIR = __DIR__ . '/uploads/site';
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
            'color' => '#10d9ff',
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

    return str_replace(__DIR__ . '/', '', $target);
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
        unset($artist['style'], $artist['year'], $artist['trackGenres']);

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
        $color = trim((string) ($_POST['genreColor'] ?? '#10d9ff'));

        if ($name === '') {
            throw new RuntimeException('Genre name is required.');
        }

        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            $color = '#10d9ff';
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
$todayIso = date('Y-m-d');
$weekAgoIso = date('Y-m-d', strtotime('-7 days'));
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SG Production Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
  :root {
    --bg: #080c10;
    --surface: #0f1419;
    --surface2: #161d26;
    --surface3: #1e2835;
    --border: #1f2d3d;
    --cyan: #00d4ff;
    --cyan-dim: rgba(0,212,255,0.12);
    --cyan-glow: rgba(0,212,255,0.35);
    --green: #00ff88;
    --green-dim: rgba(0,255,136,0.1);
    --red: #ff4560;
    --red-dim: rgba(255,69,96,0.12);
    --orange: #ff9f43;
    --orange-dim: rgba(255,159,67,0.12);
    --purple: #a855f7;
    --text: #e2eaf5;
    --text-dim: #7a8fa6;
    --text-faint: #3d5168;
    --sidebar: 220px;
    --header: 56px;
  }

  * { margin:0; padding:0; box-sizing:border-box; }
  body { background:var(--bg); color:var(--text); font-family:Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; display:flex; height:100vh; overflow:hidden; font-size:15px; font-weight:400; line-height:1.45; }

  /* SIDEBAR */
  .sidebar {
    width:var(--sidebar); min-width:var(--sidebar);
    background:var(--surface);
    border-right:1px solid var(--border);
    display:flex; flex-direction:column;
    padding:0 0 16px;
    overflow-y:auto;
  }
  .brand {
    padding:20px 18px 16px;
    border-bottom:1px solid var(--border);
    display:flex; align-items:center; gap:10px;
  }
  .brand-avatar {
    width:34px; height:34px; border-radius:8px;
    background:linear-gradient(135deg,#00d4ff,#a855f7);
    display:flex; align-items:center; justify-content:center;
    font-family:Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; font-size:16px; color:#fff;
    flex-shrink:0;
  }
  .brand-text .name { font-weight:600; font-size:13px; letter-spacing:.3px; }
  .brand-text .role { font-size:10px; color:var(--text-dim); letter-spacing:.5px; text-transform:uppercase; }

  .nav-section { padding:12px 10px 4px; }
  .nav-label { font-size:9px; color:var(--text-faint); text-transform:uppercase; letter-spacing:1.5px; padding:0 8px 6px; font-weight:600; }
  .nav-item {
    display:flex; align-items:center; gap:10px;
    padding:9px 12px; border-radius:8px; cursor:pointer;
    font-size:13px; color:var(--text-dim); font-weight:500;
    transition:all .15s; position:relative;
    text-decoration:none;
  }
  .nav-item:hover { background:var(--surface2); color:var(--text); }
  .nav-item.active { background:var(--cyan-dim); color:var(--cyan); }
  .nav-item.active::before {
    content:''; position:absolute; left:-10px; top:50%; transform:translateY(-50%);
    width:3px; height:18px; background:var(--cyan); border-radius:0 3px 3px 0;
  }
  .nav-item svg { width:15px; height:15px; flex-shrink:0; }
  .sidebar-footer {
    margin-top:auto; padding:12px 10px 0;
    border-top:1px solid var(--border);
  }
  .sidebar-scrim {
    position:fixed;
    inset:0;
    z-index:90;
    background:rgba(0,0,0,.55);
    backdrop-filter:blur(8px);
    opacity:0;
    pointer-events:none;
    transition:opacity .18s ease;
  }
  .sidebar-scrim.open {
    opacity:1;
    pointer-events:auto;
  }

  /* MAIN */
  .main { flex:1; display:flex; flex-direction:column; overflow:hidden; }

  /* TOPBAR */
  .topbar {
    height:var(--header); border-bottom:1px solid var(--border);
    background:var(--surface); padding:0 24px;
    display:flex; align-items:center; gap:16px;
    flex-shrink:0;
  }
  .mobile-menu-toggle {
    width:42px;
    height:42px;
    border:1px solid var(--border);
    border-radius:999px;
    background:var(--surface2);
    color:var(--text);
    display:none;
    align-items:center;
    justify-content:center;
    flex-direction:column;
    gap:5px;
    cursor:pointer;
    flex-shrink:0;
  }
  .mobile-menu-toggle span {
    width:17px;
    height:2px;
    border-radius:999px;
    background:currentColor;
  }
  .topbar-title { font-family:Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; font-size:22px; letter-spacing:0; color:var(--text); }
  .topbar-sub { font-size:12px; color:var(--text-dim); margin-left:4px; }
  .topbar-right { margin-left:auto; display:flex; align-items:center; gap:10px; }
  .btn {
    padding:7px 16px; border-radius:8px; border:none; cursor:pointer;
    font-family:Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; font-size:12px; font-weight:600;
    letter-spacing:.3px; transition:all .15s;
  }
  .btn-outline { background:transparent; border:1px solid var(--border); color:var(--text-dim); }
  .btn-outline:hover { border-color:var(--cyan); color:var(--cyan); }
  .btn-primary { background:var(--cyan); color:#000; }
  .btn-primary:hover { box-shadow:0 0 16px var(--cyan-glow); }
  .btn-ghost { background:transparent; border:1px solid var(--border); color:var(--text-dim); display:flex; align-items:center; gap:6px; }
  .btn-ghost:hover { background:var(--surface2); color:var(--text); }

  /* CONTENT */
  .content { flex:1; overflow-y:auto; padding:24px; display:flex; flex-direction:column; gap:20px; }
  .view-section { display:none; flex-direction:column; gap:20px; }
  .view-section.active { display:flex; }
  .section-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px; }
  .section-note { color:var(--text-dim); line-height:1.6; max-width:720px; }
  .upload-panel { max-width:1040px; }
  .admin-form { display:flex; flex-direction:column; gap:18px; }
  .form-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px; }
  .form-field { display:flex; flex-direction:column; gap:8px; color:var(--text); font-size:13px; font-weight:700; }
  .form-field.full { grid-column:1 / -1; }
  .form-field input,
  .form-field select,
  .form-field textarea {
    width:100%;
    min-height:44px;
    border:1px solid var(--border);
    border-radius:12px;
    background:var(--surface2);
    color:var(--text);
    padding:11px 13px;
    font:inherit;
    font-weight:500;
    outline:none;
    transition:border-color .15s, box-shadow .15s, background .15s;
  }
  .form-field input:focus,
  .form-field select:focus,
  .form-field textarea:focus {
    border-color:var(--cyan);
    box-shadow:0 0 0 3px var(--cyan-dim);
  }
  .form-field input[type="file"] {
    padding:9px 12px;
    color:var(--text-dim);
  }
  .form-help { color:var(--text-dim); font-size:12px; font-weight:500; line-height:1.5; }
  .duration-pill {
    display:inline-flex;
    align-items:center;
    min-height:44px;
    width:100%;
    border:1px solid var(--border);
    border-radius:12px;
    background:var(--surface2);
    color:var(--text-dim);
    padding:11px 13px;
    font-size:13px;
    font-weight:600;
  }
  .check-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; }
  .check-card {
    display:flex;
    align-items:center;
    gap:10px;
    border:1px solid var(--border);
    border-radius:12px;
    background:var(--surface2);
    color:var(--text);
    padding:13px;
    font-size:13px;
    font-weight:700;
  }
  .check-card input { width:18px; height:18px; accent-color:var(--cyan); }
  .form-actions { display:flex; justify-content:flex-end; gap:10px; padding-top:2px; }
  .artist-toolbar { display:flex; align-items:flex-start; justify-content:space-between; gap:20px; }
  .artist-toolbar h2 { font-size:20px; line-height:1.2; margin-bottom:6px; }
  .artist-toolbar p { color:var(--text-dim); font-size:14px; }
  .artist-form-layout { display:grid; grid-template-columns:minmax(0,1fr) 220px; gap:22px; align-items:start; }
  .tag-input-shell {
    min-height:44px;
    border:1px solid var(--border);
    border-radius:12px;
    background:var(--surface2);
    padding:7px;
    display:flex;
    flex-wrap:wrap;
    align-items:center;
    gap:7px;
    transition:border-color .15s, box-shadow .15s;
  }
  .tag-input-shell:focus-within {
    border-color:var(--cyan);
    box-shadow:0 0 0 3px var(--cyan-dim);
  }
  .tag-input-shell input {
    min-width:150px;
    flex:1;
    border:0;
    background:transparent;
    color:var(--text);
    padding:6px;
    outline:0;
    font:inherit;
  }
  .genre-tag,
  .artist-genre-pill {
    display:inline-flex;
    align-items:center;
    gap:6px;
    border:1px solid rgba(0,212,255,.35);
    border-radius:999px;
    background:var(--cyan-dim);
    color:var(--cyan);
    padding:4px 9px;
    font-size:12px;
    font-weight:700;
  }
  .genre-tag button {
    width:16px;
    height:16px;
    border:0;
    border-radius:50%;
    background:rgba(255,255,255,.09);
    color:inherit;
    cursor:pointer;
    line-height:1;
  }
  .image-preview-box {
    border:1px solid var(--border);
    border-radius:14px;
    background:var(--surface2);
    padding:18px;
    display:flex;
    flex-direction:column;
    align-items:center;
    gap:14px;
  }
  .artist-preview {
    width:112px;
    height:112px;
    border-radius:50%;
    border:1px solid var(--border);
    background:linear-gradient(135deg,rgba(0,212,255,.22),rgba(168,85,247,.12));
    overflow:hidden;
  }
  .artist-preview img,
  .artist-avatar img { width:100%; height:100%; object-fit:cover; display:block; }
  .file-trigger {
    width:100%;
    min-height:40px;
    border:1px solid var(--cyan);
    border-radius:999px;
    background:var(--cyan-dim);
    cursor:pointer;
    color:var(--cyan);
    font-size:13px;
    font-weight:700;
    text-align:center;
    display:flex;
    align-items:center;
    justify-content:center;
    padding:0 14px;
    transition:box-shadow .15s, background .15s;
  }
  .file-trigger:hover { box-shadow:0 0 16px var(--cyan-glow); background:rgba(0,212,255,.18); }
  .file-trigger input { display:none; }
  .selected-file-name {
    max-width:100%;
    color:var(--text-dim);
    font-size:11px;
    font-weight:600;
    text-align:center;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
  }
  .char-row { display:flex; justify-content:space-between; gap:12px; }
  .char-count { color:var(--text-dim); font-size:12px; font-weight:600; }
  .artist-grid {
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:16px;
  }
  .artist-card {
    position:relative;
    min-height:236px;
    border:1px solid var(--border);
    border-radius:14px;
    background:var(--surface);
    padding:22px 18px;
    overflow:hidden;
    display:flex;
    flex-direction:column;
    align-items:center;
    text-align:center;
    transition:border-color .15s, transform .15s, background .15s;
  }
  .artist-card:hover {
    border-color:rgba(0,212,255,.45);
    background:var(--surface2);
    transform:translateY(-2px);
  }
  .artist-avatar {
    width:84px;
    height:84px;
    border-radius:50%;
    border:1px solid var(--border);
    overflow:hidden;
    margin-bottom:14px;
    background:var(--surface3);
  }
  .artist-name { font-size:16px; font-weight:800; color:var(--text); }
  .artist-card-actions {
    position:absolute;
    inset:0;
    display:flex;
    align-items:center;
    justify-content:center;
    gap:10px;
    background:rgba(8,12,16,.82);
    backdrop-filter:blur(10px);
    opacity:0;
    pointer-events:none;
    transition:opacity .15s;
  }
  .artist-card:hover .artist-card-actions {
    opacity:1;
    pointer-events:auto;
  }
  .genre-grid {
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:16px;
  }
  .genre-card {
    position:relative;
    border:1px solid var(--border);
    border-radius:14px;
    background:var(--surface);
    padding:18px;
    overflow:hidden;
    transition:border-color .15s, transform .15s, background .15s;
  }
  .genre-card:hover {
    border-color:rgba(0,212,255,.35);
    background:var(--surface2);
    transform:translateY(-2px);
  }
  .genre-card::before {
    content:'';
    position:absolute;
    inset:0 0 auto;
    height:4px;
    background:var(--genre-color, var(--cyan));
  }
  .genre-card-head {
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:12px;
    margin-bottom:10px;
  }
  .genre-card-title {
    font-size:17px;
    font-weight:800;
    color:var(--text);
  }
  .genre-slug {
    color:var(--text-dim);
    font-family:Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    font-size:12px;
    font-weight:600;
    margin-top:4px;
  }
  .genre-card-actions {
    display:flex;
    gap:7px;
    flex-shrink:0;
  }
  .icon-btn {
    width:34px;
    height:34px;
    border:1px solid var(--border);
    border-radius:999px;
    background:var(--surface2);
    color:var(--text-dim);
    display:grid;
    place-items:center;
    cursor:pointer;
    transition:border-color .15s, color .15s, background .15s;
  }
  .icon-btn:hover {
    border-color:var(--cyan);
    color:var(--cyan);
    background:var(--cyan-dim);
  }
  .icon-btn svg {
    width:15px;
    height:15px;
  }
  .genre-description {
    min-height:40px;
    color:var(--text-dim);
    font-size:13px;
    line-height:1.5;
    margin:12px 0 14px;
  }
  .genre-counts {
    display:flex;
    flex-wrap:wrap;
    gap:8px;
  }
  .genre-card.confirming .genre-confirm {
    display:flex;
  }
  .genre-confirm {
    display:none;
    flex-direction:column;
    gap:12px;
    margin-top:14px;
    border:1px solid var(--orange);
    border-radius:12px;
    background:var(--orange-dim);
    color:var(--text);
    padding:12px;
    font-size:13px;
    font-weight:700;
  }
  .genre-confirm.error {
    display:flex;
    border-color:var(--red);
    background:var(--red-dim);
    color:var(--red);
  }
  .genre-confirm-actions {
    display:flex;
    flex-wrap:wrap;
    gap:8px;
  }
  .color-swatch-grid {
    display:flex;
    flex-wrap:wrap;
    gap:10px;
  }
  .color-swatch-option {
    width:34px;
    height:34px;
    border-radius:999px;
    border:2px solid transparent;
    padding:3px;
    cursor:pointer;
    display:grid;
    place-items:center;
  }
  .color-swatch-option input {
    display:none;
  }
  .color-swatch-option span {
    width:100%;
    height:100%;
    border-radius:inherit;
    background:var(--swatch);
    box-shadow:0 0 18px color-mix(in srgb, var(--swatch) 42%, transparent);
  }
  .color-swatch-option:has(input:checked) {
    border-color:var(--text);
    background:rgba(255,255,255,.08);
  }
  .genre-form-actions {
    display:flex;
    justify-content:flex-end;
    gap:10px;
    flex-wrap:wrap;
  }
  [data-cancel-genre-edit] {
    display:none;
  }
  .is-editing-genre [data-cancel-genre-edit] {
    display:inline-flex;
  }
  .empty-state {
    display:none;
    min-height:220px;
    border:1px dashed var(--border);
    border-radius:14px;
    background:rgba(255,255,255,.025);
    align-items:center;
    justify-content:center;
    text-align:center;
    padding:30px;
  }
  .empty-state.is-visible { display:flex; }
  .empty-state h3 { font-size:18px; margin-bottom:8px; }
  .empty-state p { color:var(--text-dim); margin-bottom:18px; }
  .management-toolbar {
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:20px;
  }
  .management-toolbar h2 { font-size:20px; line-height:1.2; margin-bottom:6px; }
  .management-toolbar p { color:var(--text-dim); font-size:14px; }
  .song-controls {
    display:flex;
    align-items:center;
    justify-content:flex-end;
    gap:10px;
    flex-wrap:wrap;
  }
  .admin-control {
    min-height:40px;
    border:1px solid var(--border);
    border-radius:999px;
    background:var(--surface2);
    color:var(--text);
    padding:9px 13px;
    font:inherit;
    font-size:13px;
    font-weight:600;
    outline:0;
  }
  .admin-control:focus {
    border-color:var(--cyan);
    box-shadow:0 0 0 3px var(--cyan-dim);
  }
  .song-search { width:240px; }
  .songs-list { display:flex; flex-direction:column; gap:10px; }
  .song-row {
    position:relative;
    display:grid;
    grid-template-columns:minmax(280px,1.45fr) 120px 110px 110px 120px 128px;
    gap:14px;
    align-items:center;
    border:1px solid var(--border);
    border-radius:14px;
    background:var(--surface);
    padding:14px;
    transition:border-color .15s, background .15s;
  }
  .song-row:hover {
    border-color:rgba(0,212,255,.35);
    background:var(--surface2);
  }
  .song-main {
    display:flex;
    align-items:center;
    gap:13px;
    min-width:0;
  }
  .song-thumb {
    width:58px;
    height:58px;
    border-radius:10px;
    overflow:hidden;
    border:1px solid var(--border);
    background:var(--surface3);
    flex-shrink:0;
  }
  .song-thumb img { width:100%; height:100%; object-fit:cover; display:block; }
  .song-title { font-size:15px; font-weight:800; color:var(--text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .song-detail { color:var(--text-dim); font-size:12px; margin-top:4px; }
  .metric-badge {
    display:inline-flex;
    justify-content:center;
    min-width:82px;
    border:1px solid var(--border);
    border-radius:999px;
    background:var(--surface2);
    color:var(--text);
    padding:7px 10px;
    font-size:12px;
    font-weight:800;
  }
  .metric-badge.cyan { color:var(--cyan); border-color:rgba(0,212,255,.25); }
  .metric-badge.orange { color:var(--orange); border-color:rgba(255,159,67,.25); }
  .status-pill {
    display:inline-flex;
    justify-content:center;
    min-width:82px;
    border-radius:999px;
    padding:7px 10px;
    font-size:12px;
    font-weight:800;
  }
  .status-pill.published { background:var(--green-dim); color:var(--green); }
  .status-pill.draft { background:var(--orange-dim); color:var(--orange); }
  .status-pill.unlisted { background:var(--red-dim); color:var(--red); }
  .song-flags { display:flex; flex-wrap:wrap; gap:6px; }
  .song-actions { display:flex; justify-content:flex-end; gap:8px; }
  .song-action-btn.is-playing {
    border-color:var(--cyan);
    color:var(--cyan);
    background:var(--cyan-dim);
  }
  .song-action-btn.danger:hover {
    border-color:var(--red);
    color:var(--red);
    background:var(--red-dim);
  }
  .song-delete-warning {
    grid-column:1 / -1;
    display:none;
    align-items:center;
    justify-content:space-between;
    gap:14px;
    border:1px solid var(--red);
    border-radius:12px;
    background:var(--red-dim);
    color:var(--text);
    padding:12px;
  }
  .song-row.confirming .song-delete-warning { display:flex; }
  .drawer-backdrop {
    position:fixed;
    inset:0;
    z-index:70;
    background:rgba(0,0,0,.45);
    opacity:0;
    pointer-events:none;
    transition:opacity .18s;
  }
  .drawer-backdrop.open {
    opacity:1;
    pointer-events:auto;
  }
  .edit-drawer {
    position:fixed;
    inset:0 0 0 auto;
    z-index:80;
    width:min(520px, calc(100vw - 24px));
    background:var(--surface);
    border-left:1px solid var(--border);
    box-shadow:-24px 0 60px rgba(0,0,0,.42);
    transform:translateX(105%);
    transition:transform .22s ease;
    display:flex;
    flex-direction:column;
  }
  .edit-drawer.open { transform:translateX(0); }
  .drawer-head {
    padding:22px;
    border-bottom:1px solid var(--border);
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:18px;
  }
  .drawer-head h3 { font-size:20px; margin-bottom:4px; }
  .drawer-head p { color:var(--text-dim); font-size:13px; }
  .drawer-body { padding:22px; overflow-y:auto; }
  .pagination {
    display:flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    padding-top:6px;
  }
  .page-btn {
    min-width:42px;
    height:42px;
    border:1px solid var(--border);
    border-radius:999px;
    background:var(--surface);
    color:var(--text);
    cursor:pointer;
    font-weight:800;
  }
  .page-btn.active,
  .page-btn:hover {
    border-color:var(--cyan);
    background:var(--cyan);
    color:#000;
  }
  .upload-layout {
    display:grid;
    grid-template-columns:minmax(0,1fr) 320px;
    gap:16px;
    align-items:start;
  }
  .upload-main-card { min-width:0; }
  .file-meta {
    display:none;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    border:1px solid var(--border);
    border-radius:12px;
    background:rgba(0,212,255,.055);
    color:var(--text-dim);
    padding:10px 12px;
    font-size:12px;
    font-weight:600;
  }
  .file-meta.is-visible { display:flex; }
  .cover-upload-row {
    display:grid;
    grid-template-columns:96px minmax(0,1fr);
    gap:14px;
    align-items:center;
  }
  .cover-preview-thumb {
    width:96px;
    aspect-ratio:1;
    border:1px solid var(--border);
    border-radius:14px;
    background:var(--surface2);
    overflow:hidden;
    display:grid;
    place-items:center;
    color:var(--text-dim);
    font-size:12px;
    font-weight:700;
  }
  .cover-preview-thumb img { width:100%; height:100%; object-fit:cover; display:block; }
  .toggle-card {
    cursor:pointer;
    justify-content:space-between;
  }
  .toggle-card input {
    appearance:none;
    width:44px;
    height:24px;
    border-radius:999px;
    background:var(--surface3);
    position:relative;
    transition:background .15s;
  }
  .toggle-card input::after {
    content:'';
    position:absolute;
    width:18px;
    height:18px;
    border-radius:50%;
    background:var(--text-dim);
    top:3px;
    left:3px;
    transition:transform .15s, background .15s;
  }
  .toggle-card input:checked {
    background:var(--cyan-dim);
  }
  .toggle-card input:checked::after {
    transform:translateX(20px);
    background:var(--cyan);
  }
  .upload-progress {
    display:none;
    border:1px solid var(--border);
    border-radius:14px;
    background:var(--surface2);
    padding:14px;
  }
  .upload-progress.is-visible { display:block; }
  .progress-head {
    display:flex;
    justify-content:space-between;
    color:var(--text-dim);
    font-size:12px;
    font-weight:700;
    margin-bottom:10px;
  }
  .progress-track {
    height:8px;
    border-radius:999px;
    background:var(--surface3);
    overflow:hidden;
  }
  .progress-fill {
    width:0%;
    height:100%;
    border-radius:inherit;
    background:linear-gradient(90deg,var(--cyan),var(--green));
    transition:width .18s;
  }
  .tips-card ul {
    list-style:none;
    display:flex;
    flex-direction:column;
    gap:14px;
    color:var(--text-dim);
    font-size:13px;
    line-height:1.5;
  }
  .tips-card li {
    border-bottom:1px solid var(--border);
    padding-bottom:14px;
  }
  .tips-card li:last-child {
    border-bottom:0;
    padding-bottom:0;
  }
  .settings-stack {
    display:flex;
    flex-direction:column;
    gap:16px;
    padding-bottom:74px;
  }
  .settings-card-grid {
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:16px;
  }
  .settings-preview {
    width:120px;
    aspect-ratio:1.91 / 1;
    border:1px solid var(--border);
    border-radius:12px;
    background:var(--surface2);
    overflow:hidden;
    display:grid;
    place-items:center;
    color:var(--text-dim);
    font-size:12px;
    font-weight:700;
  }
  .settings-preview.square {
    width:64px;
    aspect-ratio:1;
    border-radius:14px;
  }
  .settings-preview img { width:100%; height:100%; object-fit:cover; display:block; }
  .settings-upload-row {
    display:flex;
    align-items:center;
    gap:14px;
  }
  .sticky-save-bar {
    position:sticky;
    bottom:0;
    z-index:20;
    margin-top:4px;
    border:1px solid var(--border);
    border-radius:16px;
    background:rgba(15,20,25,.9);
    backdrop-filter:blur(16px);
    padding:14px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:16px;
    box-shadow:0 -14px 34px rgba(0,0,0,.24);
  }
  .save-copy { color:var(--text-dim); font-size:13px; }
  .toast {
    position:fixed;
    right:24px;
    bottom:24px;
    z-index:120;
    background:var(--surface);
    border:1px solid var(--cyan);
    color:var(--text);
    border-radius:999px;
    padding:12px 18px;
    box-shadow:0 16px 44px rgba(0,0,0,.32);
    opacity:0;
    transform:translateY(12px);
    pointer-events:none;
    transition:opacity .18s, transform .18s;
    font-weight:800;
  }
  .toast.show {
    opacity:1;
    transform:translateY(0);
  }
  .analytics-filter-bar {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:16px;
    flex-wrap:wrap;
  }
  .date-chip-group {
    display:flex;
    gap:8px;
    padding:4px;
    border:1px solid var(--border);
    border-radius:999px;
    background:var(--surface);
  }
  .date-chip {
    border:0;
    border-radius:999px;
    background:transparent;
    color:var(--text-dim);
    padding:8px 14px;
    font:inherit;
    font-size:12px;
    font-weight:800;
    cursor:pointer;
  }
  .date-chip.active,
  .date-chip:hover {
    background:var(--cyan);
    color:#000;
  }
  .custom-date-range {
    display:flex;
    align-items:center;
    gap:8px;
    flex-wrap:wrap;
    justify-content:flex-end;
  }
  .range-field {
    display:flex;
    align-items:center;
    gap:8px;
    border:1px solid var(--border);
    border-radius:999px;
    background:var(--surface);
    padding:4px 8px 4px 12px;
  }
  .range-field span {
    color:var(--text-faint);
    font-size:11px;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:.5px;
  }
  .range-field .admin-control {
    width:150px;
    min-height:34px;
    border:0;
    border-radius:999px;
    background:var(--surface2);
    padding:7px 10px;
  }
  .date-range-apply {
    min-height:42px;
    padding-inline:16px;
  }
  .analytics-kpis {
    display:grid;
    grid-template-columns:repeat(6,minmax(0,1fr));
    gap:12px;
  }
  .line-chart-shell {
    position:relative;
    min-height:360px;
    border:1px solid var(--border);
    border-radius:14px;
    background:linear-gradient(180deg,rgba(255,255,255,.035),rgba(255,255,255,.01));
    padding:18px 18px 18px;
    overflow:hidden;
  }
  .chart-summary {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    margin-bottom:12px;
    color:var(--text-dim);
    font-size:12px;
    font-weight:700;
  }
  .chart-summary strong {
    color:var(--text);
    font-size:15px;
  }
  .line-chart-shell svg {
    width:100%;
    height:100%;
    display:block;
  }
  .line-chart-shell svg line,
  .line-chart-shell svg polyline {
    vector-effect:non-scaling-stroke;
  }
  .chart-visual {
    position:relative;
    height:268px;
  }
  .chart-y-axis {
    position:absolute;
    inset:0;
    z-index:2;
    pointer-events:none;
  }
  .chart-y-axis span {
    position:absolute;
    left:0;
    transform:translateY(-50%);
    color:var(--text-faint);
    font-size:10px;
    font-weight:800;
  }
  .chart-point-layer {
    position:absolute;
    inset:0;
    z-index:3;
  }
  .chart-point {
    position:absolute;
    width:28px;
    height:28px;
    border:0;
    border-radius:50%;
    background:transparent;
    transform:translate(-50%, -50%);
    pointer-events:auto;
    cursor:pointer;
    padding:0;
  }
  .chart-point::before {
    content:'';
    position:absolute;
    left:50%;
    top:50%;
    width:12px;
    height:12px;
    border-radius:50%;
    background:var(--cyan);
    box-shadow:0 0 0 4px rgba(0,212,255,.12);
    transform:translate(-50%, -50%);
    transition:box-shadow .14s ease, transform .14s ease;
  }
  .chart-point:hover::before,
  .chart-point:focus::before,
  .chart-point:focus-visible::before {
    box-shadow:0 0 0 6px rgba(0,212,255,.16), 0 0 18px rgba(0,212,255,.38);
    transform:translate(-50%, -50%) scale(1.04);
  }
  .chart-point-value {
    position:absolute;
    left:50%;
    bottom:30px;
    transform:translateX(-50%);
    border:1px solid var(--border);
    border-radius:999px;
    padding:4px 8px;
    color:var(--text);
    background:var(--surface);
    box-shadow:0 10px 30px rgba(0,0,0,.28);
    font-size:11px;
    font-weight:800;
    opacity:0;
    white-space:nowrap;
    transition:opacity .14s ease, transform .14s ease;
  }
  .chart-point:hover .chart-point-value,
  .chart-point:focus .chart-point-value,
  .chart-point:focus-visible .chart-point-value {
    opacity:1;
    transform:translateX(-50%) translateY(-2px);
  }
  .chart-axis {
    display:flex;
    justify-content:space-between;
    color:var(--text-faint);
    font-size:11px;
    font-weight:700;
    padding:8px 8px 0 64px;
  }
  .chart-value-label {
    display:none;
  }
  .chart-toggle {
    display:flex;
    gap:6px;
    padding:3px;
    border:1px solid var(--border);
    border-radius:999px;
    background:var(--surface2);
  }
  .chart-toggle button {
    border:0;
    border-radius:999px;
    background:transparent;
    color:var(--text-dim);
    padding:7px 12px;
    font:inherit;
    font-size:12px;
    font-weight:800;
    cursor:pointer;
  }
  .chart-toggle button.active,
  .chart-toggle button:hover {
    background:var(--cyan-dim);
    color:var(--cyan);
  }
  .horizontal-bars {
    display:flex;
    flex-direction:column;
    gap:14px;
  }
  .hbar-row {
    display:grid;
    grid-template-columns:140px minmax(0,1fr) 54px;
    gap:12px;
    align-items:center;
  }
  .hbar-label {
    color:var(--text);
    font-size:13px;
    font-weight:800;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
  }
  .hbar-track {
    height:10px;
    border-radius:999px;
    background:var(--surface3);
    overflow:hidden;
  }
  .hbar-fill {
    height:100%;
    border-radius:inherit;
    background:linear-gradient(90deg,var(--cyan),var(--green));
  }
  .hbar-value {
    color:var(--text-dim);
    font-size:12px;
    font-weight:800;
    text-align:right;
  }
  .donut-wrap {
    display:grid;
    grid-template-columns:180px minmax(0,1fr);
    gap:22px;
    align-items:center;
  }
  .donut-chart {
    width:180px;
    aspect-ratio:1;
    border-radius:50%;
    background:conic-gradient(var(--cyan) 0 38%, var(--green) 38% 64%, var(--orange) 64% 84%, var(--purple) 84% 100%);
    position:relative;
  }
  .donut-chart::after {
    content:'';
    position:absolute;
    inset:34px;
    border-radius:50%;
    background:var(--surface);
    border:1px solid var(--border);
  }
  .donut-legend {
    display:flex;
    flex-direction:column;
    gap:11px;
  }
  .legend-row {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    color:var(--text-dim);
    font-size:13px;
    font-weight:700;
  }
  .legend-label {
    display:flex;
    align-items:center;
    gap:8px;
  }
  .legend-dot {
    width:9px;
    height:9px;
    border-radius:50%;
  }
  .analytics-table-wrap {
    overflow:auto;
  }
  .analytics-table {
    width:100%;
    min-width:1120px;
    border-collapse:collapse;
  }
  .analytics-table th,
  .analytics-table td {
    padding:12px 10px;
    border-bottom:1px solid var(--border);
    text-align:left;
    vertical-align:middle;
  }
  .analytics-table th {
    color:var(--text-faint);
    font-size:10px;
    text-transform:uppercase;
    letter-spacing:1px;
    font-weight:800;
    white-space:nowrap;
  }
  .analytics-table th[data-sort] {
    cursor:pointer;
  }
  .analytics-table th[data-sort]::after {
    content:' ↕';
    color:var(--text-faint);
  }
  .analytics-table td {
    color:var(--text-dim);
    font-size:13px;
    font-weight:600;
  }
  .analytics-table tr:hover td {
    background:var(--surface2);
  }
  .table-cover {
    width:42px;
    height:42px;
    border-radius:9px;
    overflow:hidden;
    border:1px solid var(--border);
  }
  .table-cover img { width:100%; height:100%; object-fit:cover; display:block; }
  .notification-tabs {
    display:flex;
    gap:8px;
    flex-wrap:wrap;
  }
  .notification-feed {
    display:flex;
    flex-direction:column;
    gap:10px;
  }
  .notification-item {
    position:relative;
    display:grid;
    grid-template-columns:42px minmax(0,1fr) auto;
    gap:14px;
    align-items:center;
    border:1px solid var(--border);
    border-left-width:4px;
    border-radius:14px;
    background:var(--surface);
    padding:14px;
    transition:border-color .15s, background .15s;
  }
  .notification-item:hover {
    background:var(--surface2);
  }
  .notification-item.download { border-left-color:var(--cyan); }
  .notification-item.ad { border-left-color:var(--green); }
  .notification-item.warning { border-left-color:var(--orange); }
  .notification-item.error { border-left-color:var(--red); }
  .notification-item.system { border-left-color:var(--purple); }
  .notification-icon {
    width:42px;
    height:42px;
    border-radius:50%;
    display:grid;
    place-items:center;
    background:var(--surface2);
    color:var(--cyan);
    font-weight:900;
    border:1px solid var(--border);
  }
  .notification-title-row {
    display:flex;
    align-items:center;
    gap:8px;
    margin-bottom:4px;
  }
  .notification-title {
    font-size:15px;
    font-weight:800;
    color:var(--text);
  }
  .unread-dot {
    width:8px;
    height:8px;
    border-radius:50%;
    background:var(--cyan);
    box-shadow:0 0 12px var(--cyan-glow);
  }
  .notification-description {
    color:var(--text-dim);
    font-size:13px;
    line-height:1.45;
  }
  .notification-time {
    color:var(--text-faint);
    font-size:12px;
    font-weight:700;
    margin-top:6px;
  }
  .dismiss-notification {
    opacity:0;
    pointer-events:none;
    transition:opacity .15s;
  }
  .notification-item:hover .dismiss-notification {
    opacity:1;
    pointer-events:auto;
  }
  .notification-settings {
    overflow:hidden;
  }
  .notification-settings-body {
    display:none;
    padding-top:16px;
  }
  .notification-settings.open .notification-settings-body {
    display:block;
  }
  .ad-status-layout {
    display:grid;
    grid-template-columns:160px minmax(0,1fr) 250px;
    gap:18px;
    align-items:center;
  }
  .ad-preview-thumb {
    width:150px;
    aspect-ratio:9 / 16;
    border:1px solid var(--border);
    border-radius:16px;
    overflow:hidden;
    background:var(--surface2);
    display:grid;
    place-items:center;
    color:var(--text-dim);
    font-size:12px;
    font-weight:800;
  }
  .ad-preview-thumb img,
  .ad-preview-thumb video {
    width:100%;
    height:100%;
    object-fit:cover;
    display:block;
  }
  .ad-detail-list {
    display:flex;
    flex-direction:column;
    gap:10px;
    color:var(--text-dim);
    font-size:13px;
  }
  .ad-detail-list strong {
    color:var(--text);
  }
  .ad-upload-layout {
    display:grid;
    grid-template-columns:minmax(0,1fr) 190px;
    gap:18px;
    align-items:start;
  }
  .ad-media-meta {
    display:none;
    flex-direction:column;
    gap:6px;
    border:1px solid var(--border);
    border-radius:12px;
    background:var(--surface2);
    padding:12px;
    color:var(--text-dim);
    font-size:12px;
    font-weight:700;
  }
  .ad-media-meta.is-visible {
    display:flex;
  }
  .ratio-warning {
    display:none;
    border:1px solid var(--orange);
    border-radius:12px;
    background:var(--orange-dim);
    color:var(--orange);
    padding:10px 12px;
    font-size:12px;
    font-weight:800;
  }
  .ratio-warning.is-visible { display:block; }
  .ad-summary-grid {
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:12px;
  }
  .history-table {
    width:100%;
    border-collapse:collapse;
    min-width:980px;
  }
  .history-table th,
  .history-table td {
    padding:12px 10px;
    border-bottom:1px solid var(--border);
    text-align:left;
    vertical-align:middle;
  }
  .history-table th {
    color:var(--text-faint);
    font-size:10px;
    text-transform:uppercase;
    letter-spacing:1px;
    font-weight:800;
  }
  .history-table td {
    color:var(--text-dim);
    font-size:13px;
    font-weight:600;
  }

  /* DATE RANGE */
  .filter-bar { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
  .filter-chip {
    padding:6px 14px; border-radius:20px; border:1px solid var(--border);
    font-size:11px; font-weight:600; cursor:pointer; color:var(--text-dim);
    background:transparent; transition:all .15s; letter-spacing:.3px;
  }
  .filter-chip:hover { border-color:var(--cyan-dim); color:var(--text); }
  .filter-chip.active { border-color:var(--cyan); color:var(--cyan); background:var(--cyan-dim); }
  .filter-sep { color:var(--text-faint); font-size:11px; }

  /* STAT CARDS */
  .stats-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; }
  .stat-card {
    background:var(--surface); border:1px solid var(--border);
    border-radius:12px; padding:18px 20px;
    position:relative; overflow:hidden;
    transition:border-color .2s;
  }
  .stat-card:hover { border-color:var(--border); }
  .stat-card::after {
    content:''; position:absolute; top:0; left:0; right:0; height:2px;
  }
  .stat-card.cyan::after { background:linear-gradient(90deg,var(--cyan),transparent); }
  .stat-card.green::after { background:linear-gradient(90deg,var(--green),transparent); }
  .stat-card.orange::after { background:linear-gradient(90deg,var(--orange),transparent); }
  .stat-card.purple::after { background:linear-gradient(90deg,var(--purple),transparent); }
  .stat-label { font-size:10px; color:var(--text-dim); text-transform:uppercase; letter-spacing:1px; font-weight:600; margin-bottom:10px; }
  .stat-value { font-family:Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; font-size:36px; letter-spacing:0; line-height:1; margin-bottom:6px; }
  .stat-value.cyan { color:var(--cyan); }
  .stat-value.green { color:var(--green); }
  .stat-value.orange { color:var(--orange); }
  .stat-value.purple { color:var(--purple); }
  .stat-change { font-size:11px; display:flex; align-items:center; gap:4px; }
  .stat-change.up { color:var(--green); }
  .stat-change.down { color:var(--red); }
  .stat-icon { position:absolute; top:16px; right:16px; opacity:.15; font-size:28px; }

  /* TWO COL */
  .two-col { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
  .three-col { display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px; }

  /* PANEL */
  .panel {
    background:var(--surface); border:1px solid var(--border);
    border-radius:12px; padding:20px;
  }
  .panel-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; }
  .panel-title { font-size:13px; font-weight:600; color:var(--text); letter-spacing:.3px; }
  .panel-action { font-size:11px; color:var(--cyan); cursor:pointer; background:none; border:none; font-family:Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; font-weight:600; }
  .panel-action:hover { text-decoration:underline; }

  /* SONG ROWS */
  .song-table { width:100%; border-collapse:collapse; }
  .song-table th {
    text-align:left; font-size:9px; color:var(--text-faint);
    text-transform:uppercase; letter-spacing:1.2px; font-weight:700;
    padding:0 12px 10px; border-bottom:1px solid var(--border);
  }
  .song-table td { padding:12px 12px; border-bottom:1px solid var(--border); font-size:12px; vertical-align:middle; }
  .song-table tr:last-child td { border-bottom:none; }
  .song-table tr:hover td { background:var(--surface2); }
  .song-cover {
    width:36px; height:36px; border-radius:6px;
    background:linear-gradient(135deg,#1a2a40,#0f1e30);
    overflow:hidden;
    display:flex; align-items:center; justify-content:center;
    font-size:16px; flex-shrink:0;
  }
  .song-cover img { width:100%; height:100%; object-fit:cover; display:block; }
  .song-info { display:flex; align-items:center; gap:10px; }
  .song-name { font-weight:600; font-size:12px; color:var(--text); }
  .song-meta { font-size:10px; color:var(--text-dim); margin-top:2px; }
  .mono { font-family:Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; font-size:11px; }
  .pill {
    display:inline-block; padding:2px 8px; border-radius:20px;
    font-size:10px; font-weight:700; letter-spacing:.3px;
  }
  .pill.green { background:var(--green-dim); color:var(--green); }
  .pill.orange { background:var(--orange-dim); color:var(--orange); }
  .pill.red { background:var(--red-dim); color:var(--red); }

  /* MINI BAR CHART */
  .bar-chart { display:flex; align-items:flex-end; gap:4px; height:50px; }
  .bar {
    flex:1; border-radius:3px 3px 0 0;
    background:var(--cyan-dim); position:relative;
    transition:all .3s;
    cursor:pointer;
  }
  .bar:hover { background:var(--cyan); }
  .bar span {
    position:absolute; bottom:-16px; left:50%; transform:translateX(-50%);
    font-size:8px; color:var(--text-faint); white-space:nowrap;
  }
  .chart-wrap { padding-bottom:20px; }

  /* AD STATS */
  .ad-stat-row { display:flex; align-items:center; justify-content:space-between; padding:10px 0; border-bottom:1px solid var(--border); }
  .ad-stat-row:last-child { border-bottom:none; }
  .ad-stat-label { font-size:12px; color:var(--text-dim); display:flex; align-items:center; gap:8px; }
  .ad-stat-label .dot { width:6px; height:6px; border-radius:50%; flex-shrink:0; }
  .ad-stat-val { font-family:Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; font-size:13px; font-weight:600; }

  /* PROGRESS BAR */
  .prog-bar { height:4px; background:var(--surface3); border-radius:2px; overflow:hidden; margin-top:6px; }
  .prog-fill { height:100%; border-radius:2px; }

  /* EXPORT BOX */
  .export-box {
    background:linear-gradient(135deg, rgba(0,212,255,0.06), rgba(168,85,247,0.06));
    border:1px solid var(--border); border-radius:12px; padding:20px;
    display:flex; align-items:center; justify-content:space-between; gap:16px;
  }
  .export-box-text .title { font-size:13px; font-weight:600; margin-bottom:4px; }
  .export-box-text .sub { font-size:11px; color:var(--text-dim); }
  .export-btns { display:flex; gap:8px; flex-shrink:0; }

  /* QUICK ACTIONS */
  .quick-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:8px; }
  .quick-card {
    background:var(--surface2); border:1px solid var(--border);
    border-radius:10px; padding:14px 12px; cursor:pointer;
    transition:all .15s; text-align:center;
    display:flex; flex-direction:column; align-items:center; gap:8px;
    color:inherit; font:inherit;
  }
  .quick-card:hover { border-color:var(--cyan); background:var(--cyan-dim); }
  .quick-card .icon { font-size:20px; }
  .quick-card .label { font-size:10px; font-weight:600; color:var(--text-dim); letter-spacing:.3px; line-height:1.3; }
  .quick-card:hover .label { color:var(--cyan); }

  /* ACTIVITY FEED */
  .activity-item { display:flex; gap:12px; padding:10px 0; border-bottom:1px solid var(--border); }
  .activity-item:last-child { border-bottom:none; }
  .activity-dot { width:8px; height:8px; border-radius:50%; margin-top:5px; flex-shrink:0; }
  .activity-text { font-size:12px; color:var(--text-dim); line-height:1.5; }
  .activity-text strong { color:var(--text); }
  .activity-time { font-size:10px; color:var(--text-faint); margin-top:2px; font-family:Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }

  /* SCROLLBAR */
  ::-webkit-scrollbar { width:4px; }
  ::-webkit-scrollbar-track { background:transparent; }
  ::-webkit-scrollbar-thumb { background:var(--border); border-radius:2px; }

  /* TABS */
  .tabs { display:flex; gap:2px; background:var(--surface2); padding:3px; border-radius:8px; }
  .tab {
    padding:5px 14px; border-radius:6px; font-size:11px; font-weight:600;
    cursor:pointer; color:var(--text-dim); transition:all .15s; border:none;
    background:transparent; font-family:Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
  }
  .tab.active { background:var(--surface); color:var(--text); box-shadow:0 1px 4px rgba(0,0,0,.3); }

  /* NOTIFICATION DOT */
  .notif { position:relative; }
  .notif::after {
    content:''; position:absolute; top:-2px; right:-2px;
    width:7px; height:7px; border-radius:50%;
    background:var(--red); border:1px solid var(--bg);
  }

  @media (max-width: 1180px) {
    .stats-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }
    .analytics-kpis { grid-template-columns:repeat(3,minmax(0,1fr)); }
    .artist-grid,
    .genre-grid { grid-template-columns:repeat(3,minmax(0,1fr)); }
    .song-row {
      grid-template-columns:minmax(240px,1fr) 120px 110px 100px;
    }
    .song-row > div:nth-child(5),
    .song-row > div:nth-child(6) {
      grid-column:auto;
    }
    .song-actions {
      grid-column:1 / -1;
      justify-content:flex-start;
    }
  }

  @media (max-width: 960px) {
    body {
      display:block;
      height:100vh;
      overflow:hidden;
    }
    .sidebar {
      position:fixed;
      inset:0 auto 0 0;
      z-index:100;
      width:min(82vw, 320px);
      min-width:0;
      max-width:320px;
      background:rgba(15,20,25,.92);
      border-right:1px solid rgba(255,255,255,.08);
      box-shadow:28px 0 70px rgba(0,0,0,.42);
      backdrop-filter:blur(22px);
      transform:translateX(-105%);
      transition:transform .22s ease;
    }
    .sidebar.open { transform:translateX(0); }
    .main {
      width:100%;
      height:100vh;
      min-width:0;
    }
    .topbar {
      min-height:64px;
      height:auto;
      padding:10px 14px;
      gap:10px;
      background:rgba(15,20,25,.9);
      backdrop-filter:blur(18px);
    }
    .topbar > div:not(.topbar-right) {
      min-width:0;
    }
    .mobile-menu-toggle { display:flex; }
    .topbar-title {
      display:block;
      font-size:20px;
      line-height:1.1;
      white-space:nowrap;
      overflow:hidden;
      text-overflow:ellipsis;
    }
    .topbar-sub {
      display:block;
      margin:3px 0 0;
      font-size:12px;
    }
    .topbar-right { gap:8px; }
    .topbar-right .btn-ghost { display:none; }
    #topPrimaryAction {
      padding:9px 12px;
      white-space:nowrap;
    }
    .content {
      padding:16px;
      gap:16px;
    }
    .view-section { gap:16px; }
    .two-col,
    .three-col,
    .section-grid,
    .upload-layout,
    .artist-form-layout,
    .settings-card-grid,
    .ad-status-layout,
    .ad-upload-layout {
      grid-template-columns:1fr;
    }
    .artist-grid,
    .genre-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }
    .ad-summary-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }
    .ad-preview-thumb { width:min(180px, 100%); }
    .management-toolbar,
    .artist-toolbar,
    .analytics-filter-bar,
    .panel-header,
    .export-box,
    .sticky-save-bar {
      align-items:stretch;
      flex-direction:column;
    }
    .song-controls {
      display:grid;
      grid-template-columns:1fr;
      width:100%;
    }
    .song-search,
    .admin-control {
      width:100%;
    }
    .custom-date-range {
      justify-content:flex-start;
      width:100%;
    }
    .range-field {
      flex:1 1 180px;
    }
    .range-field .admin-control {
      width:100%;
    }
    .date-range-apply {
      flex:1 1 120px;
    }
    .panel,
    .analytics-table-wrap {
      overflow-x:auto;
    }
    .song-table {
      min-width:520px;
    }
    .hbar-row {
      grid-template-columns:110px minmax(0,1fr) 48px;
    }
    .donut-wrap {
      grid-template-columns:1fr;
      justify-items:center;
    }
    .donut-legend {
      width:100%;
    }
    .line-chart-shell {
      min-height:260px;
      padding:14px 12px 30px;
    }
    .chart-visual { height:210px; }
    .chart-axis {
      overflow-x:auto;
      gap:22px;
    }
    .chart-toggle,
    .date-chip-group,
    .notification-tabs,
    .pagination {
      overflow-x:auto;
      justify-content:flex-start;
      padding-bottom:3px;
    }
    .date-chip,
    .page-btn,
    .notification-tabs .btn {
      flex:0 0 auto;
    }
    .notification-item {
      grid-template-columns:40px minmax(0,1fr);
      align-items:flex-start;
    }
    .dismiss-notification {
      grid-column:2;
      opacity:1;
      pointer-events:auto;
      justify-self:flex-start;
    }
  }

  @media (max-width: 620px) {
    body { font-size:14px; }
    .brand { padding:18px 16px 14px; }
    .nav-item {
      min-height:44px;
      border-radius:12px;
      font-size:15px;
    }
    .content { padding:14px 12px 18px; }
    .topbar { padding:9px 12px; }
    .topbar-title { font-size:17px; }
    .topbar-sub { display:none; }
    #topPrimaryAction {
      max-width:128px;
      overflow:hidden;
      text-overflow:ellipsis;
    }
    .stats-grid,
    .analytics-kpis,
    .ad-summary-grid,
    .form-grid,
    .check-grid,
    .artist-grid,
    .genre-grid,
    .quick-grid {
      grid-template-columns:1fr;
    }
    .stat-card,
    .panel {
      border-radius:16px;
      padding:16px;
    }
    .stat-value { font-size:30px; }
    .song-row {
      grid-template-columns:1fr;
      gap:10px;
      padding:14px;
      border-radius:18px;
    }
    .song-main { align-items:flex-start; }
    .song-thumb {
      width:54px;
      height:54px;
      border-radius:12px;
    }
    .song-title { white-space:normal; }
    .metric-badge,
    .status-pill,
    .artist-genre-pill {
      width:fit-content;
    }
    .song-actions {
      justify-content:flex-start;
      display:flex;
      gap:8px;
    }
    .song-delete-warning {
      flex-direction:column;
      align-items:flex-start;
    }
    .song-delete-warning > div {
      display:flex;
      gap:8px;
      flex-wrap:wrap;
    }
    .cover-upload-row,
    .settings-upload-row {
      grid-template-columns:1fr;
      display:grid;
    }
    .cover-preview-thumb { width:112px; }
    .edit-drawer {
      width:100vw;
      border-left:0;
    }
    .drawer-head,
    .drawer-body {
      padding:18px 16px;
    }
    .ad-preview-thumb {
      width:min(220px, 100%);
      justify-self:start;
    }
    .toast {
      right:12px;
      left:12px;
      bottom:14px;
      text-align:center;
      border-radius:16px;
    }
  }

  /* Typography tuned to match the main SG Production website. */
  body,
  button,
  input,
  select,
  .brand-avatar,
  .topbar-title,
  .stat-value,
  .mono,
  .ad-stat-val,
  .activity-time {
    font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
  }
  .brand-text .name { font-size:15px; font-weight:700; }
  .brand-text .role { font-size:11px; }
  .nav-label { font-size:10px; letter-spacing:1.2px; }
  .nav-item { font-size:14px; font-weight:500; }
  .topbar-title { font-size:23px; font-weight:800; letter-spacing:0; }
  .topbar-sub { font-size:13px; }
  .btn { font-size:13px; border-radius:999px; }
  .filter-chip { font-size:12px; }
  .stat-label { font-size:11px; }
  .stat-value { font-size:32px; font-weight:800; }
  .stat-change { font-size:12px; }
  .panel-title { font-size:15px; font-weight:700; }
  .panel-action { font-size:12px; }
  .song-table th { font-size:10px; }
  .song-table td { font-size:13px; }
  .song-name { font-size:14px; }
  .song-meta { font-size:11px; }
  .mono,
  .ad-stat-val { font-size:12px; }
  .status { font-size:11px; }
  .chart-label { font-size:9px; }
  .ad-stat-label,
  .activity-text { font-size:13px; }
  .export-box-text .title { font-size:14px; }
  .export-box-text .sub,
  .quick-card .label,
  .activity-time { font-size:11px; }

      .notice{margin:16px 24px 0;border:1px solid var(--border);border-radius:12px;padding:12px 14px;font-weight:700;color:var(--text)}
      .notice.error{border-color:var(--red);background:var(--red-dim)}
      .notice.success{border-color:var(--green);background:var(--green-dim)}
      .login-shell{min-height:100vh;display:grid;place-items:center;padding:24px;background:radial-gradient(circle at 20% 20%,rgba(0,212,255,.12),transparent 34%),var(--bg)}
      .login-card{width:min(620px,100%)}
      .brand-avatar svg{width:22px;height:22px;fill:#fff}
      .song-delete-warning form,.genre-confirm-actions form,.inline-form{display:inline-flex;margin:0}
      .admin-form input[type=file]{padding:12px;background:var(--surface2)}
      .song-row.is-hidden{display:none!important}
      .track-edit-panel{display:none;grid-column:1/-1;border-top:1px solid var(--border);padding-top:16px;margin-top:4px}
      .song-row.editing .track-edit-panel{display:block}
      .topbar-right form{margin:0}
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
          <div class="nav-section"><div class="nav-label">Overview</div><a class="nav-item active" href="#dashboard" data-section="dashboard"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>Dashboard</a><a class="nav-item" href="#analytics" data-section="analytics"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>Analytics</a></div>
          <div class="nav-section"><div class="nav-label">Music</div><a class="nav-item" href="#upload" data-section="upload"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>Upload New Song</a><a class="nav-item" href="#songs" data-section="songs"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>Uploaded Songs</a><a class="nav-item" href="#artists" data-section="artists"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>Artist Management</a><a class="nav-item" href="#genres" data-section="genres"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1116 0z"/><circle cx="12" cy="10" r="3"/></svg>Genre Management</a></div>
          <div class="nav-section"><div class="nav-label">Monetization</div><a class="nav-item" href="#advertising" data-section="advertising"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v16"/></svg>Advertising</a></div>
          <div class="nav-section"><div class="nav-label">Site</div><a class="nav-item" href="#settings" data-section="settings"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93l-1.41 1.41M4.93 4.93l1.41 1.41M4.93 19.07l1.41-1.41M19.07 19.07l-1.41-1.41M1 12h2M21 12h2M12 1v2M12 21v2"/></svg>Website Settings</a><a class="nav-item notif" href="#notifications" data-section="notifications"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>Notifications</a></div>
        </nav>
        <div class="sidebar-footer"><a class="nav-item" href="/"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>View Live Site</a></div>
      </aside>
      <div class="sidebar-scrim" id="sidebarScrim"></div>
      <div class="main">
        <div class="topbar">
          <button class="mobile-menu-toggle" id="mobileMenuToggle" type="button" aria-label="Open admin menu" aria-expanded="false"><span></span><span></span><span></span></button>
          <div><span class="topbar-title">Dashboard</span><span class="topbar-sub">— <?= e(date('M Y')) ?></span></div>
          <div class="topbar-right"><button class="btn btn-ghost" type="button" onclick="alert('Report export will use real analytics once tracking is available.')">Export Report</button><button class="btn btn-primary" id="topPrimaryAction" data-action-section="upload" type="button">+ Upload Song</button><form method="post"><input type="hidden" name="action" value="logout"><button class="btn btn-outline" type="submit">Log Out</button></form></div>
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
              <div class="panel"><div class="panel-header"><span class="panel-title">Ad Performance Breakdown</span><button class="panel-action" type="button" onclick="alert('CSV export will use real analytics once available.')">Export CSV →</button></div>
                <?php $adRows = [['Total Impressions','cyan',$statText($adImpressions,$hasAdData),$hasAdData ? 85 : 0],['Unique Viewers','green','N/A',0],['Total Ad Clicks','orange',$statText($adClicks,$hasAdData),$hasAdData ? 25 : 0],['Click-Through Rate','purple',$statSmallText($ctr,$hasAdData,'%'),$hasAdData ? 45 : 0],['Avg. Time-on-Ad-Page','red','N/A',0]]; ?>
                <?php foreach ($adRows as $row): ?><div class="ad-stat-row"><div class="ad-stat-label"><div class="dot" style="background:var(--<?= e($row[1]) ?>)"></div><?= e($row[0]) ?></div><div><div class="ad-stat-val" style="color:var(--<?= e($row[1]) ?>)"><?= e((string) $row[2]) ?></div><div class="prog-bar" style="width:120px"><div class="prog-fill" style="width:<?= e((string) $row[3]) ?>%;background:var(--<?= e($row[1]) ?>)"></div></div></div></div><?php endforeach; ?>
                <div style="margin-top:16px;padding:12px;background:var(--surface2);border-radius:8px;border:1px solid var(--border)"><div style="font-size:10px;color:var(--text-dim);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;font-weight:700;">Downloads Trend (7 days)</div><div class="chart-wrap"><div class="bar-chart"><?php foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $day): ?><div class="bar" style="height:10%"><span><?= e($day) ?></span></div><?php endforeach; ?></div></div></div>
              </div>
            </div>
            <div class="three-col"><div class="panel"><div class="panel-header"><span class="panel-title">Quick Actions</span></div><div class="quick-grid"><button class="quick-card" type="button" data-action-section="upload"><div class="icon"></div><div class="label">Upload Song</div></button><button class="quick-card" type="button" data-action-section="advertising"><div class="icon"></div><div class="label">Update Ad</div></button><button class="quick-card" type="button" data-action-section="songs"><div class="icon"></div><div class="label">Feature Song</div></button><button class="quick-card" type="button" onclick="alert('CSV export will use real analytics once available.')"><div class="icon"></div><div class="label">Export CSV</div></button><button class="quick-card" type="button" data-action-section="artists"><div class="icon"></div><div class="label">Add Artist</div></button><button class="quick-card" type="button" onclick="alert('No WAV link selected.')"><div class="icon"></div><div class="label">Copy WAV Link</div></button></div></div>
              <div class="panel"><div class="panel-header"><span class="panel-title">Recent Activity</span><button class="panel-action" type="button">Clear</button></div><div><div class="activity-item"><div class="activity-dot" style="background:var(--green)"></div><div><div class="activity-text"><?= $latestTrack ? '<strong>' . e((string) ($latestTrack['title'] ?? 'N/A')) . '</strong> uploaded' : 'N/A' ?></div><div class="activity-time"><?= $latestTrack ? 'Latest catalog update' : 'No activity yet' ?></div></div></div><div class="activity-item"><div class="activity-dot" style="background:var(--cyan)"></div><div><div class="activity-text">Ad clicks: <strong><?= e($statText($adClicks,$hasAdData)) ?></strong></div><div class="activity-time">Live ad stats</div></div></div></div></div>
              <div class="panel"><div class="panel-header"><span class="panel-title">Advertiser Report</span></div><div style="font-size:11px;color:var(--text-dim);margin-bottom:14px;line-height:1.6;">Share this report with brands to show your ad performance. Includes impressions, clicks, CTR, and top songs.</div><div style="background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:12px;margin-bottom:12px;"><div style="font-size:10px;color:var(--text-faint);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;font-weight:700;">Report Preview</div><div style="font-size:11px;color:var(--text-dim);display:flex;flex-direction:column;gap:5px;"><div style="display:flex;justify-content:space-between"><span>Period</span><span class="mono" style="color:var(--text)">N/A</span></div><div style="display:flex;justify-content:space-between"><span>Impressions</span><span class="mono" style="color:var(--cyan)"><?= e($statText($adImpressions,$hasAdData)) ?></span></div><div style="display:flex;justify-content:space-between"><span>Clicks</span><span class="mono" style="color:var(--orange)"><?= e($statText($adClicks,$hasAdData)) ?></span></div><div style="display:flex;justify-content:space-between"><span>CTR</span><span class="mono" style="color:var(--green)"><?= e($statSmallText($ctr,$hasAdData,'%')) ?></span></div></div></div><div style="display:flex;flex-direction:column;gap:6px;"><button class="btn btn-primary" style="width:100%;text-align:center;padding:9px;" type="button">Download PDF Report</button><button class="btn btn-outline" style="width:100%;text-align:center;padding:9px;" type="button">Export as CSV</button><button class="btn btn-outline" style="width:100%;text-align:center;padding:9px;color:var(--cyan);border-color:var(--cyan-dim);" type="button">Copy Shareable Link</button></div></div></div>
          </section>

          <section class="view-section" id="analytics-section" data-title="Analytics" data-subtitle="Performance overview"><div class="management-toolbar"><div><h2>Analytics</h2><p>Measure downloads, song views, ads, and engagement</p></div><button class="btn btn-primary" type="button" id="analyticsExportCsv">Export CSV</button></div><div class="analytics-filter-bar"><div class="date-chip-group"><button class="date-chip active" type="button" data-analytics-range="7D">7D</button><button class="date-chip" type="button" data-analytics-range="30D">30D</button><button class="date-chip" type="button" data-analytics-range="90D">90D</button><button class="date-chip" type="button" data-analytics-range="all">All Time</button></div><div class="custom-date-range"><label class="range-field"><span>From</span><input class="admin-control" id="analyticsStartDate" type="date" value="<?= e($weekAgoIso) ?>"></label><label class="range-field"><span>To</span><input class="admin-control" id="analyticsEndDate" type="date" value="<?= e($todayIso) ?>"></label><button class="btn btn-outline date-range-apply" type="button" id="analyticsApplyRange">Apply</button></div></div><div class="analytics-kpis"><div class="stat-card cyan"><div class="stat-label">Total Downloads</div><div class="stat-value cyan"><?= e($statText($totalDownloads,$hasDownloadData)) ?></div><div class="stat-change up">N/A vs previous period</div></div><div class="stat-card green"><div class="stat-label">Total Song Page Views</div><div class="stat-value green">N/A</div><div class="stat-change up">N/A vs previous period</div></div><div class="stat-card orange"><div class="stat-label">Ad Impressions</div><div class="stat-value orange"><?= e($statText($adImpressions,$hasAdData)) ?></div><div class="stat-change up">N/A vs previous period</div></div><div class="stat-card purple"><div class="stat-label">Ad Clicks</div><div class="stat-value purple"><?= e($statText($adClicks,$hasAdData)) ?></div><div class="stat-change up">N/A vs previous period</div></div><div class="stat-card cyan"><div class="stat-label">CTR Percentage</div><div class="stat-value cyan"><?= e($statSmallText($ctr,$hasAdData,'%')) ?></div><div class="stat-change down">N/A vs previous period</div></div><div class="stat-card green"><div class="stat-label">Avg Session Duration</div><div class="stat-value green">N/A</div><div class="stat-change up">N/A vs previous period</div></div></div><div class="panel"><div class="panel-header"><span class="panel-title">Downloads Over Time</span><div class="chart-toggle" id="analyticsChartToggle"><button class="active" type="button" data-chart="Downloads">Downloads</button><button type="button" data-chart="Page Views">Page Views</button><button type="button" data-chart="Ad Clicks">Ad Clicks</button></div></div><div class="line-chart-shell"><div class="chart-summary"><span><strong id="analyticsChartTotal">N/A</strong> total downloads</span><span id="analyticsChartPeak">Peak: N/A</span></div><div class="chart-visual"><div class="chart-y-axis" aria-hidden="true"><span style="top:11.5%">1,000</span><span style="top:32.7%">750</span><span style="top:53.8%">500</span><span style="top:75%">250</span><span style="top:91.5%">0</span></div><svg viewBox="0 0 900 260" preserveAspectRatio="none"><g stroke="rgba(255,255,255,.08)" stroke-width="1"><line x1="70" y1="30" x2="880" y2="30"></line><line x1="70" y1="85" x2="880" y2="85"></line><line x1="70" y1="140" x2="880" y2="140"></line><line x1="70" y1="195" x2="880" y2="195"></line><line x1="70" y1="238" x2="880" y2="238"></line></g><polyline id="analyticsLine" fill="none" stroke="#00d4ff" stroke-width="5" stroke-linecap="round" stroke-linejoin="round" points="80,238 194,238 308,238 422,238 536,238 650,238 764,238 875,238"></polyline></svg><div class="chart-point-layer" id="analyticsPointLayer"></div></div><div class="chart-axis"><span>Day 1</span><span>Day 2</span><span>Day 3</span><span>Day 4</span><span>Day 5</span><span>Day 6</span><span>Day 7</span><span>Today</span></div></div></div><div class="two-col"><div class="panel"><div class="panel-header"><span class="panel-title">Top 5 Songs by Downloads</span></div><div class="horizontal-bars"><?php if ($topTracks === []): ?><div class="hbar-row"><div class="hbar-label">N/A</div><div class="hbar-track"><div class="hbar-fill" style="width:0%"></div></div><div class="hbar-value">N/A</div></div><?php else: ?><?php foreach ($topTracks as $track): ?><div class="hbar-row"><div class="hbar-label"><?= e((string) ($track['title'] ?? 'N/A')) ?></div><div class="hbar-track"><div class="hbar-fill" style="width:<?= $hasDownloadData ? '40' : '0' ?>%"></div></div><div class="hbar-value"><?= e($hasDownloadData ? number_format($downloadCountFor($track)) : 'N/A') ?></div></div><?php endforeach; ?><?php endif; ?></div></div><div class="panel"><div class="panel-header"><span class="panel-title">Traffic by Genre</span></div><div class="donut-wrap"><div class="donut-chart"></div><div class="donut-legend"><?php $colors = ['cyan','green','orange','purple']; $i=0; foreach (array_slice($genreUsage,0,4,true) as $genreName=>$count): $percent = $genreTotal > 0 ? round(($count / $genreTotal) * 100) : null; ?><div class="legend-row"><span class="legend-label"><span class="legend-dot" style="background:var(--<?= e($colors[$i] ?? 'cyan') ?>)"></span><?= e((string) $genreName) ?></span><strong><?= e($percent !== null ? $percent . '%' : 'N/A') ?></strong></div><?php $i++; endforeach; ?></div></div></div></div><div class="panel"><div class="panel-header"><span class="panel-title">Per-Song Analytics</span></div><div class="analytics-table-wrap"><table class="analytics-table" id="analyticsTable"><thead><tr><th data-sort="index">#</th><th>Cover</th><th data-sort="title">Song Title</th><th data-sort="artist">Artist</th><th data-sort="genre">Genre</th><th data-sort="views">Page Views</th><th data-sort="downloads">Downloads</th><th data-sort="impressions">Ad Impressions</th><th data-sort="clicks">Ad Clicks</th><th data-sort="ctr">CTR</th><th data-sort="time">Avg Time on Page</th></tr></thead><tbody><?php if ($trackCount === 0): ?><tr data-index="0" data-title="N/A" data-artist="N/A" data-genre="N/A" data-views="0" data-downloads="0" data-impressions="0" data-clicks="0" data-ctr="0" data-time="0"><td>N/A</td><td>N/A</td><td>N/A</td><td>N/A</td><td>N/A</td><td>N/A</td><td>N/A</td><td>N/A</td><td>N/A</td><td>N/A</td><td>N/A</td></tr><?php else: ?><?php foreach ($tracks as $index => $track): if (!is_array($track)) continue; $trackId=(string)($track['id']??''); $songStats=is_array($adSongs[$trackId]??null)?$adSongs[$trackId]:[]; $rowImpressions=(int)($songStats['impressions']??0); $rowClicks=(int)($songStats['clicks']??0); $rowCtr=$rowImpressions>0?round(($rowClicks/$rowImpressions)*100,2):null; $rowDownloads=$downloadCountFor($track); ?><tr data-index="<?= e((string)($index+1)) ?>" data-title="<?= e((string)($track['title']??'N/A')) ?>" data-artist="<?= e((string)($track['artist']??'N/A')) ?>" data-genre="<?= e((string)($track['genre']??'N/A')) ?>" data-views="0" data-downloads="<?= e((string)$rowDownloads) ?>" data-impressions="<?= e((string)$rowImpressions) ?>" data-clicks="<?= e((string)$rowClicks) ?>" data-ctr="<?= e((string)($rowCtr??0)) ?>" data-time="0"><td><?= e(str_pad((string)($index+1),2,'0',STR_PAD_LEFT)) ?></td><td><div class="table-cover"><img src="<?= e((string)($track['cover']??'assets/cover-1.jpg')) ?>" alt=""></div></td><td><?= e((string)($track['title']??'N/A')) ?></td><td><?= e((string)($track['artist']??'N/A')) ?></td><td><?= e((string)($track['genre']??'N/A')) ?></td><td>N/A</td><td><?= e($hasDownloadData ? number_format($rowDownloads) : 'N/A') ?></td><td><?= e($hasAdData ? number_format($rowImpressions) : 'N/A') ?></td><td><?= e($hasAdData ? number_format($rowClicks) : 'N/A') ?></td><td><?= e($hasAdData && $rowCtr !== null ? $rowCtr . '%' : 'N/A') ?></td><td>N/A</td></tr><?php endforeach; ?><?php endif; ?></tbody></table></div><div class="pagination" id="analyticsPagination"></div></div></section>

          <section class="view-section" id="upload-section" data-title="Upload New Song" data-subtitle="Add the public preview file and the WAV download link separately"><div class="management-toolbar"><div><h2>Upload New Song</h2><p>Add the public preview file and the WAV download link separately</p></div></div><div class="upload-layout"><div class="panel upload-main-card"><div class="panel-header"><span class="panel-title">Song Details</span></div><form class="admin-form" id="uploadSongForm" method="post" enctype="multipart/form-data"><input type="hidden" name="action" value="upload"><div class="form-grid"><label class="form-field">Song Title<input type="text" name="title" placeholder="Nagin Theme" required></label><label class="form-field">Artist<input type="text" name="artist" value="SG Production"></label><label class="form-field">Artist Profile<select name="artistId"><?php foreach ($artists as $artistOption): if (!is_array($artistOption)) continue; ?><option value="<?= e((string) ($artistOption['id'] ?? '')) ?>"><?= e((string) ($artistOption['name'] ?? 'Artist')) ?></option><?php endforeach; ?></select></label><label class="form-field">Genre<select name="genre"><?php foreach ($genreNames as $genreName): ?><option><?= e($genreName) ?></option><?php endforeach; ?></select></label><label class="form-field full">Preview Song File<input id="uploadPreviewFile" type="file" name="audio" accept=".wav,.mp3,audio/wav,audio/mpeg" required><span class="form-help">Accepts MP3/WAV. Duration will be detected from this file.</span><div class="file-meta" id="previewFileMeta"><span id="previewFileName">No file selected</span><span id="previewFileSize">0 MB</span></div></label><label class="form-field">Duration<input id="uploadDurationInput" type="text" name="duration" placeholder="0:0"></label><label class="form-field">Wave Style<select name="wave"><option value="sine">Sine</option><option value="square">Square</option><option value="sawtooth">Sawtooth</option><option value="triangle">Triangle</option></select></label><label class="form-field"><span>BPM</span><input type="number" name="bpm" value="124" min="40" max="240"></label><label class="form-field full">WAV Download URL<input type="url" name="downloadUrl" placeholder="https://example.com/downloads/nagin-theme.wav" required><span class="form-help">Direct URL for the full-quality WAV download.</span></label><label class="form-field full">Cover Image<div class="cover-upload-row"><div class="cover-preview-thumb" id="uploadCoverPreview">Cover</div><div><input id="uploadCoverInput" type="file" name="cover" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required><span class="form-help">Image preview appears after selection.</span></div></div></label><label class="form-field full">Credit Text<textarea name="creditText" rows="3" placeholder="Optional credit text shown on song page."></textarea></label></div><div class="check-grid"><label class="check-card toggle-card"><span>Show in Latest Releases</span><input type="checkbox" name="isNew" checked></label><label class="check-card toggle-card"><span>Mark as Featured</span><input type="checkbox" name="isFeatured"></label></div><div class="upload-progress" id="uploadProgress"><div class="progress-head"><span>Uploading song</span><span id="uploadProgressValue">0%</span></div><div class="progress-track"><div class="progress-fill" id="uploadProgressFill"></div></div></div><div class="form-actions"><button class="btn btn-outline" type="button" id="saveDraftButton">Save as Draft</button><button class="btn btn-primary" type="submit">Upload Song</button></div></form></div><aside class="panel tips-card"><div class="panel-header"><span class="panel-title">Upload Tips</span></div><ul><li><strong>Cover image:</strong> recommended 1:1 ratio, minimum 500x500px.</li><li><strong>Preview file:</strong> MP3, maximum 10MB for fast public playback.</li><li><strong>WAV link:</strong> should be a direct downloadable URL.</li><li><strong>Wave style:</strong> affects how the waveform looks on the public song page.</li></ul></aside></div></section>

          <section class="view-section" id="songs-section" data-title="Uploaded Songs" data-subtitle="Manage and monitor all your tracks"><div class="management-toolbar"><div><h2>Uploaded Songs</h2><p>Manage and monitor all your tracks</p></div><div class="song-controls"><input class="admin-control song-search" id="songSearchInput" type="search" placeholder="Search songs"><select class="admin-control" id="songGenreFilter"><option value="all">All Genres</option><?php foreach ($genreNames as $genreName): ?><option><?= e($genreName) ?></option><?php endforeach; ?></select><select class="admin-control" id="songSortSelect"><option value="newest">Newest</option><option value="downloads">Most Downloaded</option><option value="az">A-Z</option></select></div></div><div class="empty-state <?= $trackCount === 0 ? 'is-visible' : '' ?>" id="songsEmptyState"><div><h3>No songs uploaded yet</h3><p>Upload your first song to start building the catalog.</p><button class="btn btn-primary" type="button" data-action-section="upload">Upload Your First Song</button></div></div><div class="songs-list" id="songsList" style="display:<?= $trackCount > 0 ? 'flex' : 'none' ?>">
            <?php foreach ($tracks as $track): if (!is_array($track)) continue; $trackId = (string) ($track['id'] ?? ''); $title = (string) ($track['title'] ?? 'N/A'); $genre = (string) ($track['genre'] ?? 'N/A'); $duration = (string) ($track['duration'] ?? 'N/A'); $artistName = (string) ($track['artist'] ?? 'SG Production'); $downloads = $downloadCountFor($track); $adTrackClicks = $adClickCountFor($track); $status = 'Published'; ?>
              <article class="song-row" id="track-<?= e($trackId) ?>" data-id="<?= e($trackId) ?>" data-title="<?= e($title) ?>" data-artist="<?= e($artistName) ?>" data-artist-id="<?= e((string) ($track['artistId'] ?? '')) ?>" data-genre="<?= e($genre) ?>" data-duration="<?= e($duration) ?>" data-download-url="<?= e((string) ($track['downloadUrl'] ?? '')) ?>" data-bpm="<?= e((string) ($track['bpm'] ?? 124)) ?>" data-wave="<?= e((string) ($track['wave'] ?? 'sine')) ?>" data-credit="<?= e((string) ($track['creditText'] ?? '')) ?>" data-downloads="<?= e((string) $downloads) ?>" data-clicks="<?= e((string) $adTrackClicks) ?>" data-featured="<?= !empty($track['isFeatured']) ? 'true' : 'false' ?>" data-new="<?= !empty($track['isNew']) ? 'true' : 'false' ?>" data-status="<?= e($status) ?>" data-cover="<?= e((string) ($track['cover'] ?? 'assets/cover-1.jpg')) ?>" data-date="<?= e((string) ($track['createdAt'] ?? '')) ?>"><div class="song-main"><div class="song-thumb"><img src="<?= e((string) ($track['cover'] ?? 'assets/cover-1.jpg')) ?>" alt="<?= e($title) ?> cover"></div><div><div class="song-title"><?= e($title) ?></div><div class="song-detail"><?= e($artistName) ?> · <?= e($genre) ?> · <?= e($duration) ?></div></div></div><div><span class="metric-badge cyan"><?= e($hasDownloadData ? number_format($downloads) : 'N/A') ?> downloads</span></div><div><span class="metric-badge orange"><?= e($hasAdData ? number_format($adTrackClicks) : 'N/A') ?> ad clicks</span></div><div class="song-flags"><?php if (!empty($track['isFeatured'])): ?><span class="artist-genre-pill">Featured</span><?php endif; ?></div><div><span class="status-pill published">Published</span></div><div class="song-actions"><button class="icon-btn song-action-btn" type="button" data-edit-song aria-label="Edit song"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4 12.5-12.5z"/></svg></button><a class="icon-btn song-action-btn" href="<?= e((string) ($track['previewUrl'] ?? '#')) ?>" aria-label="Play song"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 5v14l11-7-11-7z"/></svg></a><button class="icon-btn song-action-btn danger" type="button" data-delete-song aria-label="Delete song"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/></svg></button></div><div class="song-delete-warning"><span>Are you sure you want to delete <?= e($title) ?>? This cannot be undone.</span><div><button class="btn btn-outline" type="button" data-cancel-delete>Cancel</button><form method="post"><input type="hidden" name="action" value="delete_track"><input type="hidden" name="trackId" value="<?= e($trackId) ?>"><button class="btn btn-primary" type="submit">Confirm Delete</button></form></div></div><div class="track-edit-panel"><form class="admin-form" method="post" enctype="multipart/form-data"><input type="hidden" name="action" value="update_track"><input type="hidden" name="trackId" value="<?= e($trackId) ?>"><div class="form-grid"><label class="form-field">Song Title<input name="title" value="<?= e($title) ?>" required></label><label class="form-field">Artist<input name="artist" value="<?= e($artistName) ?>"></label><label class="form-field">Artist Profile<select name="artistId"><?php foreach ($artists as $artistOption): if (!is_array($artistOption)) continue; ?><option value="<?= e((string) ($artistOption['id'] ?? '')) ?>" <?= (string) ($artistOption['id'] ?? '') === (string) ($track['artistId'] ?? '') ? 'selected' : '' ?>><?= e((string) ($artistOption['name'] ?? 'Artist')) ?></option><?php endforeach; ?></select></label><label class="form-field">Genre<select name="genre"><?php foreach ($genreNames as $genreName): ?><option <?= $genreName === $genre ? 'selected' : '' ?>><?= e($genreName) ?></option><?php endforeach; ?></select></label><label class="form-field">Duration<input name="duration" value="<?= e($duration) ?>"></label><label class="form-field">BPM<input type="number" name="bpm" value="<?= e((string) ($track['bpm'] ?? 124)) ?>"></label><label class="form-field">Wave Style<select name="wave"><?php foreach (['sine'=>'Sine','square'=>'Square','sawtooth'=>'Sawtooth','triangle'=>'Triangle'] as $waveValue=>$waveLabel): ?><option value="<?= e($waveValue) ?>" <?= (string) ($track['wave'] ?? 'sine') === $waveValue ? 'selected' : '' ?>><?= e($waveLabel) ?></option><?php endforeach; ?></select></label><label class="form-field full">WAV Download URL<input type="url" name="downloadUrl" value="<?= e((string) ($track['downloadUrl'] ?? '')) ?>" required></label><label class="form-field">Replace Cover<input type="file" name="cover" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"></label><label class="form-field">Replace Preview<input type="file" name="audio" accept=".wav,.mp3,audio/wav,audio/mpeg"></label><label class="form-field full">Credit Text<textarea name="creditText" rows="3"><?= e((string) ($track['creditText'] ?? '')) ?></textarea></label></div><div class="check-grid"><label class="check-card toggle-card"><span>Show in Latest Releases</span><input type="checkbox" name="isNew" <?= !empty($track['isNew']) ? 'checked' : '' ?>></label><label class="check-card toggle-card"><span>Mark as Featured</span><input type="checkbox" name="isFeatured" <?= !empty($track['isFeatured']) ? 'checked' : '' ?>></label></div><div class="form-actions"><button class="btn btn-primary" type="submit">Save Changes</button></div></form></div></article>
            <?php endforeach; ?>
          </div><div class="pagination" id="songsPagination"></div></section>

          <section class="view-section" id="artists-section" data-title="Artist Management" data-subtitle="Add artist profiles for the website"><div class="artist-toolbar"><div><h2>Artist Management</h2><p>Add artist profiles for the website</p></div><button class="btn btn-primary" type="button" data-artist-focus>+ Add New Artist</button></div><div class="panel"><div class="panel-header"><span class="panel-title">Add / Edit Artist</span></div><form class="admin-form" id="artistForm" method="post" enctype="multipart/form-data"><input type="hidden" name="action" value="save_artist"><div class="artist-form-layout"><div class="form-grid"><label class="form-field">Artist Name<input id="artistNameInput" type="text" name="artistName" placeholder="SG Production" required></label></div><div class="image-preview-box"><div class="artist-preview"><img id="artistPreviewImage" data-artist-preview="new" src="assets/artist-photo-1.svg" alt="Artist profile preview"></div><label class="file-trigger">Choose Artist Profile Image<input id="artistImageInput" data-artist-image-input data-preview-target="new" data-file-name-target="new" type="file" name="artistImage" accept=".jpg,.jpeg,.png,.webp,.svg,image/jpeg,image/png,image/webp,image/svg+xml"></label><span class="selected-file-name" id="artistImageFileName" data-file-name="new">No image selected</span><span class="form-help">Circular preview. Square image recommended.</span></div></div><div class="form-actions"><button class="btn btn-outline" type="reset" data-clear-artist>Clear Form</button><button class="btn btn-primary" type="submit">Save Artist</button></div></form></div><div class="panel"><div class="panel-header"><span class="panel-title">Existing Artists</span></div><div class="empty-state <?= $artistCount === 0 ? 'is-visible' : '' ?>" id="artistEmptyState"><div><h3>No artists added yet</h3><p>Add your first artist profile to connect songs and covers.</p><button class="btn btn-primary" type="button" data-artist-focus>Add Your First Artist</button></div></div><div class="artist-grid" id="artistGrid" style="display:<?= $artistCount > 0 ? 'grid' : 'none' ?>"><?php foreach ($artists as $artist): if (!is_array($artist)) continue; $artistId=(string)($artist['id']??''); ?><article class="artist-card"><div class="artist-avatar"><img src="<?= e((string) ($artist['image'] ?? 'assets/artist-photo-1.svg')) ?>" alt="<?= e((string) ($artist['name'] ?? 'Artist')) ?>"></div><div class="artist-name"><?= e((string) ($artist['name'] ?? 'Artist')) ?></div><div class="artist-card-actions"><details class="editor"><summary class="btn btn-primary">Edit</summary><form class="admin-form" method="post" enctype="multipart/form-data"><input type="hidden" name="action" value="save_artist"><input type="hidden" name="artistId" value="<?= e($artistId) ?>"><label class="form-field">Artist Name<input type="text" name="artistName" value="<?= e((string) ($artist['name'] ?? '')) ?>" required></label><label class="form-field">Replace Image<input type="file" name="artistImage" accept=".jpg,.jpeg,.png,.webp,.svg,image/jpeg,image/png,image/webp,image/svg+xml"></label><button class="btn btn-primary" type="submit">Save</button></form></details><form method="post" onsubmit="return confirm('Delete this artist?');"><input type="hidden" name="action" value="delete_artist"><input type="hidden" name="artistId" value="<?= e($artistId) ?>"><button class="btn btn-outline" type="submit">Delete</button></form></div></article><?php endforeach; ?></div></div></section>

          <section class="view-section" id="genres-section" data-title="Genre Management" data-subtitle="Add and manage genres for your songs and artists"><div class="management-toolbar"><div><h2>Genre Management</h2><p>Add and manage genres for your songs and artists</p></div></div><div class="panel" id="genreFormPanel"><div class="panel-header"><span class="panel-title">Add New Genre</span></div><form class="admin-form" id="genreForm" method="post"><input type="hidden" name="action" value="save_genre"><div class="form-grid"><label class="form-field">Genre Name<input id="genreNameInput" name="genreName" placeholder="Original Mix" required></label><label class="form-field">Genre Slug<input id="genreSlugInput" name="genreSlug" placeholder="original-mix"></label><label class="form-field full"><span class="char-row"><span>Genre Description</span><span class="char-count"><span id="genreDescriptionCount">0</span>/150</span></span><textarea id="genreDescriptionInput" name="genreDescription" maxlength="150" rows="3"></textarea></label><label class="form-field">Genre Color<input type="color" name="genreColor" value="#10d9ff"></label></div><div class="form-actions"><button class="btn btn-outline" type="reset" data-clear-genre>Clear</button><button class="btn btn-primary" id="genreSubmitButton" type="submit">Save Genre</button></div></form></div><div class="panel"><div class="panel-header"><span class="panel-title">Existing Genres</span><input class="admin-control" id="genreSearchInput" type="search" placeholder="Search genres"></div><div class="genre-grid" id="genreGrid"><?php foreach ($genres as $genre): if (!is_array($genre)) continue; $genreName=(string)($genre['name']??'Genre'); $counts=genreUsageCounts($genreName,$tracks,$artists); ?><article class="genre-card" style="--genre-color:<?= e((string)($genre['color']??'#10d9ff')) ?>" data-name="<?= e($genreName) ?>"><div class="genre-card-head"><div><div class="genre-card-title"><?= e($genreName) ?></div><div class="genre-slug"><?= e((string)($genre['slug']??'')) ?></div></div><div class="genre-card-actions"><details class="editor"><summary class="icon-btn" aria-label="Edit genre">✎</summary><form class="admin-form" method="post"><input type="hidden" name="action" value="save_genre"><input type="hidden" name="genreId" value="<?= e((string)($genre['id']??'')) ?>"><label class="form-field">Genre Name<input name="genreName" value="<?= e($genreName) ?>" required></label><label class="form-field">Genre Slug<input name="genreSlug" value="<?= e((string)($genre['slug']??'')) ?>"></label><label class="form-field">Color<input type="color" name="genreColor" value="<?= e((string)($genre['color']??'#10d9ff')) ?>"></label><label class="form-field full">Description<textarea name="genreDescription" rows="3"><?= e((string)($genre['description']??'')) ?></textarea></label><button class="btn btn-primary" type="submit">Update Genre</button></form></details><form method="post" onsubmit="return confirm('Deleting this genre will unassign it from all songs and artists. Continue?');"><input type="hidden" name="action" value="delete_genre"><input type="hidden" name="genreId" value="<?= e((string)($genre['id']??'')) ?>"><button class="icon-btn" type="submit" aria-label="Delete genre">×</button></form></div></div><div class="genre-description"><?= e((string)($genre['description']??'')) ?></div><div class="genre-counts"><span class="metric-badge cyan"><?= e((string)$counts['songs']) ?> songs</span><span class="metric-badge orange"><?= e((string)$counts['artists']) ?> artists</span></div></article><?php endforeach; ?></div></div></section>

          <section class="view-section" id="advertising-section" data-title="Advertising" data-subtitle="Manage ads shown on song pages"><div class="management-toolbar"><div><h2>Advertising</h2><p>Manage ads shown on song pages</p></div></div><div class="panel"><div class="panel-header"><span class="panel-title">Current Ad Status</span><span class="status-pill <?= $adEnabled ? 'published' : 'unlisted' ?>" id="adStatusPill"><?= $adEnabled ? 'Active' : 'Inactive' ?></span></div><div class="ad-status-layout"><div class="ad-preview-thumb"><?php if ($adMediaUrl !== ''): ?><?php if ($adMediaType === 'video'): ?><video src="<?= e($adMediaUrl) ?>" muted loop playsinline></video><?php else: ?><img src="<?= e($adMediaUrl) ?>" alt="Current advertisement preview"><?php endif; ?><?php else: ?>N/A<?php endif; ?></div><div class="ad-detail-list"><div><strong>File:</strong> <?= e($adMediaUrl !== '' ? basename($adMediaUrl) : 'N/A') ?></div><div><strong>Type:</strong> <?= e($adMediaType !== '' ? $adMediaType : 'N/A') ?></div><div><strong>Click URL:</strong> <?= e($adLinkUrl !== '' ? $adLinkUrl : 'N/A') ?></div><div><strong>Last updated:</strong> N/A</div></div></div></div><div class="panel"><div class="panel-header"><span class="panel-title">Update Ad</span></div><form class="admin-form" id="adUpdateForm" method="post" enctype="multipart/form-data"><input type="hidden" name="action" value="save_ad"><div class="ad-upload-layout"><div class="form-grid"><label class="form-field full">Advertising Media<input id="adMediaInput" type="file" name="adMedia" accept=".jpg,.jpeg,.png,.webp,.mp4,.webm,.mov,image/jpeg,image/png,image/webp,video/mp4,video/webm"><span class="form-help">Accepts JPG, PNG, WEBP, MP4, WEBM, or MOV. 9:16 ratio recommended.</span></label><div class="ad-media-meta full" id="adMediaMeta"><span id="adFileName">No file selected</span><span id="adFileSize">0 MB</span><span id="adDimensions">Dimensions pending</span></div><div class="ratio-warning full" id="adRatioWarning">For best results use 9:16 aspect ratio</div><label class="form-field full">Advertisement Click URL<input id="adClickUrlInput" type="url" name="adLinkUrl" value="<?= e($adLinkUrl) ?>"></label><label class="check-card toggle-card"><span>Show advertisement on single song pages</span><input id="sitewideAdToggle" type="checkbox" name="adEnabled" <?= $adEnabled ? 'checked' : '' ?>></label></div><div class="ad-preview-thumb" id="adSelectedPreview">Preview</div></div><div class="form-actions"><button class="btn btn-primary" type="submit">Save Advertising</button></div></form></div><div class="panel"><div class="panel-header"><span class="panel-title">Ad Performance Summary</span><button class="panel-action" type="button" data-action-section="analytics">View Full Ad Report →</button></div><div class="ad-summary-grid"><div class="stat-card cyan"><div class="stat-label">Total Impressions</div><div class="stat-value cyan"><?= e($statText($adImpressions,$hasAdData)) ?></div><div class="stat-change up">This month</div></div><div class="stat-card orange"><div class="stat-label">Total Clicks</div><div class="stat-value orange"><?= e($statText($adClicks,$hasAdData)) ?></div><div class="stat-change up">This month</div></div><div class="stat-card purple"><div class="stat-label">CTR</div><div class="stat-value purple"><?= e($statSmallText($ctr,$hasAdData,'%')) ?></div><div class="stat-change up">Campaign average</div></div><div class="stat-card green"><div class="stat-label">Top Song</div><div class="stat-value green" style="font-size:24px;">N/A</div><div class="stat-change up">N/A ad clicks</div></div></div></div><div class="panel"><div class="panel-header"><span class="panel-title">Ad History</span></div><div class="analytics-table-wrap"><table class="history-table"><thead><tr><th>Thumbnail</th><th>File Name</th><th>Period Active</th><th>Total Impressions</th><th>Total Clicks</th><th>CTR</th><th>Click URL</th></tr></thead><tbody><tr><td><?php if ($adMediaUrl !== ''): ?><div class="table-cover"><?php if ($adMediaType === 'video'): ?><video src="<?= e($adMediaUrl) ?>" muted loop playsinline></video><?php else: ?><img src="<?= e($adMediaUrl) ?>" alt=""><?php endif; ?></div><?php else: ?>N/A<?php endif; ?></td><td><?= e($adMediaUrl !== '' ? basename($adMediaUrl) : 'N/A') ?></td><td>N/A</td><td><?= e($statText($adImpressions,$hasAdData)) ?></td><td><?= e($statText($adClicks,$hasAdData)) ?></td><td><?= e($statSmallText($ctr,$hasAdData,'%')) ?></td><td><?= e($adLinkUrl !== '' ? $adLinkUrl : 'N/A') ?></td></tr></tbody></table></div></div></section>

          <section class="view-section" id="settings-section" data-title="Website Settings" data-subtitle="Site controls"><div class="management-toolbar"><div><h2>Website Settings</h2><p>Control public content, SEO, downloads, layout, and social links</p></div></div><form class="settings-stack" id="websiteSettingsForm" method="post" enctype="multipart/form-data"><input type="hidden" name="action" value="save_site"><div class="settings-card-grid"><div class="panel"><div class="panel-header"><span class="panel-title">Website Content</span></div><div class="admin-form"><label class="form-field">Website Title<input type="text" name="siteTitle" value="<?= e((string)($site['title']??'SG Production')) ?>"></label><label class="form-field">Tagline<input type="text" name="tagline" value="<?= e((string)($site['tagline']??'')) ?>"></label><label class="form-field">YouTube Subscribe Link<input type="url" name="youtube" value="<?= e((string)($links['youtube']??'')) ?>"></label></div></div><div class="panel"><div class="panel-header"><span class="panel-title">SEO</span></div><div class="admin-form"><label class="form-field">Default Page Title<input type="text" value="SG Production - Original Music Downloads" disabled></label><label class="form-field">Clean URL Format<input type="text" value="https://sgproduction.music/song-name" disabled></label><label class="form-field">Default Share Title<input type="text" value="Download music from SG Production" disabled></label></div></div></div><div class="panel"><div class="panel-header"><span class="panel-title">SEO & META</span></div><div class="form-grid"><label class="form-field full"><span class="char-row"><span>Meta Description</span><span class="char-count"><span id="metaDescriptionCount">0</span>/160</span></span><textarea id="metaDescriptionInput" name="metaDescription" rows="3" maxlength="160"><?= e((string)($seo['metaDescription']??'')) ?></textarea></label><label class="form-field">OG Image<input id="ogImageInput" type="file" name="ogImage" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"><span class="form-help">Current: <?= e((string)($seo['ogImage']??'N/A')) ?></span></label><label class="form-field">Favicon<input id="faviconInput" type="file" name="favicon" accept=".ico,.png,.svg,image/png,image/svg+xml"><span class="form-help">Current: <?= e((string)($seo['favicon']??'N/A')) ?></span></label><label class="form-field full">Google Analytics ID<input type="text" placeholder="G-XXXXXXXXXX" disabled></label></div></div><div class="panel"><div class="panel-header"><span class="panel-title">HOMEPAGE LAYOUT</span></div><div class="form-grid"><label class="form-field">Homepage Hero Text<input type="text" name="youtubeHeading" value="<?= e((string)($site['youtubeHeading']??'')) ?>"></label><label class="form-field">Homepage Sub-text<input type="text" name="youtubeText" value="<?= e((string)($site['youtubeText']??'')) ?>"></label><label class="form-field">Latest Count<input type="number" name="latestCount" min="0" max="12" value="<?= e((string)($catalog['latestCount']??5)) ?>"></label><label class="form-field">Songs Per Page<input type="number" name="tracksPerPage" min="5" max="50" value="<?= e((string)($catalog['tracksPerPage']??15)) ?>"></label><label class="form-field">Demo Page Count<input type="number" name="paginationDemoPages" min="1" max="40" value="<?= e((string)($catalog['paginationDemoPages']??12)) ?>"></label></div></div><div class="panel"><div class="panel-header"><span class="panel-title">SOCIAL LINKS</span></div><div class="form-grid"><label class="form-field">Instagram<input type="url" name="instagram" value="<?= e((string)($links['instagram']??'')) ?>"></label><label class="form-field">YouTube<input type="url" name="youtube" value="<?= e((string)($links['youtube']??'')) ?>"></label><label class="form-field">Spotify<input type="url" name="spotify" value="<?= e((string)($links['spotify']??'')) ?>"></label><label class="form-field">Apple Music<input type="url" name="appleMusic" value="<?= e((string)($links['appleMusic']??'')) ?>"></label><label class="form-field">Contact Email<input type="email" name="contactEmail" value="<?= e((string)($site['contactEmail']??'')) ?>"></label></div></div><div class="sticky-save-bar"><div><strong>Website Settings</strong><div class="save-copy">Save layout, SEO, download, and social changes together.</div></div><button class="btn btn-primary" type="submit">Save All Settings</button></div></form></section>

          <section class="view-section" id="notifications-section" data-title="Notifications" data-subtitle="Recent alerts"><div class="management-toolbar"><div><h2>Notifications</h2><p>Track downloads, ad activity, system alerts, and errors</p></div><button class="btn btn-primary" type="button" id="markAllReadButton">Mark All as Read</button></div><div class="notification-tabs"><button class="date-chip active" type="button" data-notification-filter="all">All</button><button class="date-chip" type="button" data-notification-filter="download">Downloads</button><button class="date-chip" type="button" data-notification-filter="ad">Ad Activity</button><button class="date-chip" type="button" data-notification-filter="system">System</button><button class="date-chip" type="button" data-notification-filter="error">Errors</button></div><div class="notification-feed" id="notificationFeed"><article class="notification-item system" data-type="system" data-unread="false"><div class="notification-icon">S</div><div><div class="notification-title-row"><span class="notification-title">N/A</span></div><div class="notification-description">No live notification feed is connected yet.</div><div class="notification-time">N/A</div></div><button class="btn btn-outline dismiss-notification" type="button">Dismiss</button></article></div><div class="panel notification-settings" id="notificationSettingsPanel"><div class="panel-header"><span class="panel-title">Notification Settings</span><button class="panel-action" type="button" id="toggleNotificationSettings">Show Settings</button></div><div class="notification-settings-body"><div class="check-grid"><label class="check-card toggle-card"><span>Email on every download</span><input type="checkbox"></label><label class="check-card toggle-card"><span>Email on ad clicks</span><input type="checkbox" checked></label><label class="check-card toggle-card"><span>Storage warnings</span><input type="checkbox" checked></label><label class="check-card toggle-card"><span>Broken link detection</span><input type="checkbox" checked></label></div></div></div></section>
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
        if (artistImageFileName) artistImageFileName.textContent = 'No image selected';
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
  const adSelectedPreview = document.querySelector('#adSelectedPreview');
  const adMediaMeta = document.querySelector('#adMediaMeta');
  const adFileName = document.querySelector('#adFileName');
  const adFileSize = document.querySelector('#adFileSize');
  const adDimensions = document.querySelector('#adDimensions');
  const adRatioWarning = document.querySelector('#adRatioWarning');
  const sitewideAdToggle = document.querySelector('#sitewideAdToggle');
  const adStatusPill = document.querySelector('#adStatusPill');

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
    Downloads: { points: '80,238 194,238 308,238 422,238 536,238 650,238 764,238 875,238', values: ['N/A','N/A','N/A','N/A','N/A','N/A','N/A','N/A'], total: 'N/A', label: 'total downloads', peak: 'Peak: N/A' },
    'Page Views': { points: '80,238 194,238 308,238 422,238 536,238 650,238 764,238 875,238', values: ['N/A','N/A','N/A','N/A','N/A','N/A','N/A','N/A'], total: 'N/A', label: 'total page views', peak: 'Peak: N/A' },
    'Ad Clicks': { points: '80,238 194,238 308,238 422,238 536,238 650,238 764,238 875,238', values: ['N/A','N/A','N/A','N/A','N/A','N/A','N/A','N/A'], total: 'N/A', label: 'total ad clicks', peak: 'Peak: N/A' }
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
  document.querySelector('#analyticsApplyRange')?.addEventListener('click', () => {
    const start = document.querySelector('#analyticsStartDate')?.value;
    const end = document.querySelector('#analyticsEndDate')?.value;
    if (!start || !end) return showToast('Select start and end dates');
    if (start > end) return showToast('Start date must be before end date');
    showToast('Analytics range applied: ' + start + ' to ' + end);
  });
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
  document.querySelector('#analyticsExportCsv')?.addEventListener('click', () => showToast('CSV export will use live analytics when tracking has data'));
  renderAnalyticsTable();

  const notificationFeed = document.querySelector('#notificationFeed');
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
