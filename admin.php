<?php
declare(strict_types=1);

session_start();

const ADMIN_PASSWORD = 'hyqhyp-viKfa3-timfaw';
const TRACKS_FILE = __DIR__ . '/data/tracks.json';
const SETTINGS_FILE = __DIR__ . '/data/settings.json';
const ARTISTS_FILE = __DIR__ . '/data/artists.json';
const COVER_DIR = __DIR__ . '/uploads/covers';
const AUDIO_DIR = __DIR__ . '/uploads/audio';
const AD_DIR = __DIR__ . '/uploads/ads';
const ARTIST_DIR = __DIR__ . '/uploads/artists';
const MAX_COVER_BYTES = 8 * 1024 * 1024;
const MAX_AUDIO_BYTES = 120 * 1024 * 1024;
const MAX_AD_BYTES = 60 * 1024 * 1024;

$errors = [];
$success = '';

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function ensureStorage(): void
{
    foreach ([dirname(TRACKS_FILE), COVER_DIR, AUDIO_DIR, AD_DIR, ARTIST_DIR] as $directory) {
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
            'isNew' => isset($_POST['isNew']),
            'isFeatured' => isset($_POST['isFeatured']) || isset($_POST['isNew']),
            'bpm' => $bpm,
            'tone' => 146.83,
            'wave' => in_array($wave, ['sine', 'triangle', 'sawtooth', 'square'], true) ? $wave : 'sine',
        ]);

        writeTracks($tracks);
        $_SESSION['flash_success'] = 'Song uploaded successfully.';
        header('Location: admin.php#uploaded-songs');
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
        header('Location: admin.php#global-settings');
        exit;
    } catch (Throwable $error) {
        $errors[] = $error->getMessage();
    }
}

if ($isAuthed && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_site') {
    try {
        $settings = readSettings();
        $latestCount = max(1, min(12, (int) ($_POST['latestCount'] ?? 5)));
        $tracksPerPage = max(5, min(50, (int) ($_POST['tracksPerPage'] ?? 15)));
        $paginationDemoPages = max(1, min(40, (int) ($_POST['paginationDemoPages'] ?? 12)));

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
        $settings['catalog'] = [
            'latestCount' => $latestCount,
            'tracksPerPage' => $tracksPerPage,
            'paginationDemoPages' => $paginationDemoPages,
        ];

        writeSettings($settings);
        $_SESSION['flash_success'] = 'Website settings saved.';
        header('Location: admin.php#website-settings');
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
        $track['isNew'] = isset($_POST['isNew']);
        $track['isFeatured'] = isset($_POST['isFeatured']);
        $track['bpm'] = $bpm;
        $track['wave'] = in_array($wave, ['sine', 'triangle', 'sawtooth', 'square'], true) ? $wave : 'sine';

        $tracks[$index] = $track;
        writeTracks($tracks);
        $_SESSION['flash_success'] = 'Song updated.';
        header('Location: admin.php#track-' . rawurlencode($trackId));
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
        header('Location: admin.php#uploaded-songs');
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
        header('Location: admin.php#track-' . rawurlencode($trackId));
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
        $artist['style'] = trim((string) ($_POST['artistStyle'] ?? 'Original Mix')) ?: 'Original Mix';
        $artist['year'] = trim((string) ($_POST['artistYear'] ?? date('Y'))) ?: date('Y');
        $artist['trackGenres'] = parseList((string) ($_POST['artistGenres'] ?? 'Original Mix'));

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

$settings = readSettings();
$tracks = readTracks();
$artists = readArtists();
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
        color-scheme: dark;
        --bg: #07090c;
        --panel: #11151a;
        --line: #242a31;
        --text: #f4f8fb;
        --muted: #8a929d;
        --cyan: #10d9ff;
      }

      * {
        box-sizing: border-box;
      }

      body {
        margin: 0;
        min-height: 100vh;
        background:
          radial-gradient(circle at 20% 0%, rgba(16, 217, 255, 0.12), transparent 30%),
          var(--bg);
        color: var(--text);
        font-family: Inter, system-ui, sans-serif;
      }

      a {
        color: inherit;
        text-decoration: none;
      }

      .admin-shell {
        width: min(1120px, calc(100% - 32px));
        margin: 0 auto;
        padding: 34px 0 48px;
      }

      .admin-header,
      .panel {
        border: 1px solid var(--line);
        border-radius: 8px;
        background: rgba(17, 21, 26, 0.92);
      }

      .admin-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
        padding: 18px;
      }

      .brand {
        display: flex;
        align-items: center;
        gap: 12px;
        font-weight: 700;
      }

      .logo {
        width: 38px;
        height: 38px;
        display: grid;
        place-items: center;
        border: 1px solid rgba(16, 217, 255, 0.42);
        border-radius: 8px;
        background: #020305;
        box-shadow: 0 0 24px rgba(16, 217, 255, 0.35);
      }

      .logo svg {
        width: 24px;
        height: 24px;
        fill: #fff;
      }

      .admin-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
      }

      .button {
        min-height: 40px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid rgba(255, 255, 255, 0.12);
        border-radius: 8px;
        padding: 0 14px;
        color: var(--text);
        background: rgba(255, 255, 255, 0.045);
        font: inherit;
        font-weight: 600;
        cursor: pointer;
      }

      .button.primary {
        border-color: rgba(16, 217, 255, 0.5);
        color: #061117;
        background: var(--cyan);
      }

      .panel {
        margin-top: 18px;
        padding: 22px;
      }

      h1,
      h2 {
        margin: 0;
      }

      h1 {
        font-size: 1.45rem;
      }

      h2 {
        font-size: 1rem;
      }

      .muted {
        color: var(--muted);
      }

      form.grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
        margin-top: 18px;
      }

      label {
        display: grid;
        gap: 8px;
        color: #c8d0d8;
        font-weight: 600;
      }

      input,
      select {
        width: 100%;
        min-height: 44px;
        border: 1px solid var(--line);
        border-radius: 8px;
        padding: 0 12px;
        color: var(--text);
        background: #0b0f14;
        font: inherit;
      }

      input[type="file"] {
        padding: 10px 12px;
      }

      .duration-status {
        min-height: 44px;
        display: flex;
        align-items: center;
        border: 1px solid var(--line);
        border-radius: 8px;
        padding: 0 12px;
        color: var(--muted);
        background: #0b0f14;
      }

      .full {
        grid-column: 1 / -1;
      }

      .check-row {
        display: flex;
        align-items: center;
        gap: 10px;
      }

      .check-row input {
        width: 18px;
        min-height: 18px;
      }

      .notice {
        margin-top: 16px;
        border-radius: 8px;
        padding: 12px 14px;
      }

      .notice.success {
        color: #061117;
        background: var(--cyan);
      }

      .notice.error {
        color: #ffd8d8;
        background: rgba(255, 69, 69, 0.16);
      }

      .dashboard-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
        margin-top: 18px;
      }

      .metric-card {
        border: 1px solid var(--line);
        border-radius: 8px;
        padding: 14px;
        background: #0b0f14;
      }

      .metric-card strong {
        display: block;
        margin-top: 8px;
        font-size: 1.35rem;
      }

      .split-heading {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
      }

      .track-list {
        display: grid;
        gap: 10px;
        margin-top: 18px;
      }

      .track-row {
        display: grid;
        grid-template-columns: 54px minmax(0, 1fr) auto;
        align-items: center;
        gap: 12px;
        border: 1px solid var(--line);
        border-radius: 8px;
        padding: 10px;
        background: #0b0f14;
      }

      .track-row img {
        width: 54px;
        height: 54px;
        border-radius: 7px;
        object-fit: cover;
      }

      .track-row strong,
      .track-row span {
        display: block;
      }

      .track-row span {
        margin-top: 4px;
        color: var(--muted);
        font-size: 0.86rem;
      }

      .track-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 10px;
        flex-wrap: wrap;
      }

      .mini-form {
        display: inline-flex;
        margin: 0;
      }

      details.editor {
        grid-column: 1 / -1;
        border-top: 1px solid var(--line);
        padding-top: 10px;
      }

      details.editor summary {
        color: var(--cyan);
        cursor: pointer;
        font-weight: 700;
      }

      .danger {
        color: #ffb8b8;
        border-color: rgba(255, 69, 69, 0.38);
        background: rgba(255, 69, 69, 0.12);
      }

      .artist-admin-list {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
        margin-top: 18px;
      }

      .artist-admin-card {
        border: 1px solid var(--line);
        border-radius: 8px;
        padding: 12px;
        background: #0b0f14;
      }

      .artist-admin-head {
        display: grid;
        grid-template-columns: 58px minmax(0, 1fr);
        gap: 12px;
        align-items: center;
        margin-bottom: 12px;
      }

      .artist-admin-head img {
        width: 58px;
        height: 58px;
        border-radius: 8px;
        object-fit: cover;
        background: #020305;
      }

      .ad-preview {
        display: grid;
        grid-template-columns: minmax(90px, 140px) minmax(0, 1fr);
        align-items: center;
        gap: 16px;
        margin-top: 18px;
        border: 1px solid var(--line);
        border-radius: 8px;
        padding: 12px;
        background: #0b0f14;
      }

      .ad-preview-media {
        overflow: hidden;
        width: 100%;
        aspect-ratio: 9 / 16;
        border-radius: 8px;
        background: #020305;
      }

      .ad-preview-media img,
      .ad-preview-media video {
        width: 100%;
        height: 100%;
        display: block;
        object-fit: cover;
      }

      @media (max-width: 700px) {
        .admin-header,
        form.grid {
          grid-template-columns: 1fr;
        }

        .admin-header {
          align-items: flex-start;
          flex-direction: column;
        }

        form.grid {
          display: grid;
        }

        .track-row {
          grid-template-columns: 48px minmax(0, 1fr);
        }

        .track-actions {
          grid-column: 1 / -1;
          justify-content: flex-start;
        }

        .ad-preview {
          grid-template-columns: 1fr;
        }

        .dashboard-grid,
        .artist-admin-list {
          grid-template-columns: 1fr;
        }
      }
    </style>
  </head>
  <body>
    <main class="admin-shell">
      <header class="admin-header">
        <a class="brand" href="/">
          <span class="logo" aria-hidden="true">
            <svg viewBox="0 0 924.99 924.99">
              <path d="M462.5,29.1C223.14,29.1,29.09,223.13,29.09,462.49s194.04,433.4,433.41,433.4,433.4-194.04,433.4-433.4S701.85,29.1,462.5,29.1ZM396.31,77.91c119.98-18.73,242.41,17.48,330.88,97.19.61.97.89,2.6.3,3.59-.52.86-14.82,8.69-17.55,10.64-66.73,47.6-86.98,143.28-38.26,210.05,26.92,36.89,76.07,63.03,87.3,109.49,21.68,89.7-79.17,162.38-161.71,116.2-65.77-36.81-62.88-113.82-98.69-170.64-39.1-62.05-128.89-110.83-202.42-120.84-65.04-8.85-136.38,4.88-193.7,35.32-8.94,4.75-17.67,11.81-25.96,16.12-1.17.61-1.84,1.27-3.41.91C101.31,228.39,233.34,103.34,396.31,77.91ZM766.38,712.49c-42.6,51.02-103.4,92.93-166.99,115.77-72.56,26.07-151.51,30.37-227.09,14.04l.54-3.69c12.76-23.05,29.02-45.59,41.26-68.76,11.02-20.85,10.09-42.73-11.49-56.68-40.28-26.01-88.01-46.32-128.88-72.06-45.54-19.86-81.75,39.73-39.25,67.97,26.92,17.89,60.26,31.49,87.8,49.03l2.55,4.15-30.94,52.47c-33.41-14.67-65.65-35.41-93-59.08-63.91-55.34-115.64-141.93-126.7-225.04-.44-3.32-1.67-9.89-.86-12.77.97-3.48,14.34-18.69,17.66-22.26,80.64-86.45,217.11-90.89,308.89-17.77,49.8,39.68,52.57,78.1,73.37,132.34,42.89,111.84,163.49,157.84,274.78,105.21l28.06-16.41c.81.8-8.46,12.07-9.71,13.56ZM843.9,520.41c-10.05-65.88-41.93-89.65-82.83-137.27-30.99-36.08-51.56-79.23-12.11-119.52,6.64-6.78,26.39-19.85,35.79-20.66,1.72-.15,2.46,1.48,3.36,2.5,3.17,3.58,7.69,11.7,10.42,16.15,47.35,77.21,63.52,171.78,47.27,260.2-1.53,1.42-1.71-.13-1.9-1.41Z"></path>
            </svg>
          </span>
          <span>
            <strong>SG Production</strong>
            <span class="muted">Admin Panel</span>
          </span>
        </a>
        <div class="admin-actions">
          <a class="button" href="/">View Site</a>
          <?php if ($isAuthed): ?>
            <form method="post">
              <input type="hidden" name="action" value="logout">
              <button class="button" type="submit">Log Out</button>
            </form>
          <?php endif; ?>
        </div>
      </header>

      <?php foreach ($errors as $message): ?>
        <div class="notice error"><?= e($message) ?></div>
      <?php endforeach; ?>

      <?php if ($success !== ''): ?>
        <div class="notice success"><?= e($success) ?></div>
      <?php endif; ?>

      <?php if (!$isAuthed): ?>
        <section class="panel">
          <h1>Log In</h1>
          <p class="muted">Use your admin password to upload songs.</p>
          <form class="grid" method="post">
            <input type="hidden" name="action" value="login">
            <label class="full">
              Password
              <input type="password" name="password" required>
            </label>
            <div class="full">
              <button class="button primary" type="submit">Open Admin</button>
            </div>
          </form>
        </section>
      <?php else: ?>
        <?php
          $site = is_array($settings['site'] ?? null) ? $settings['site'] : [];
          $links = is_array($settings['links'] ?? null) ? $settings['links'] : [];
          $catalog = is_array($settings['catalog'] ?? null) ? $settings['catalog'] : [];
          $featuredCount = count(array_filter($tracks, static fn ($track): bool => is_array($track) && !empty($track['isFeatured'] ?? $track['isNew'] ?? false)));
          $storageUsed = folderSize(__DIR__ . '/uploads');
          $lastTrack = is_array($tracks[0] ?? null) ? $tracks[0] : null;
        ?>
        <section class="panel" id="dashboard">
          <div class="split-heading">
            <div>
              <h1>Dashboard</h1>
              <p class="muted">Quick status for the live catalog.</p>
            </div>
            <a class="button" href="admin-preview.html">Open Local Preview</a>
          </div>
          <div class="dashboard-grid">
            <div class="metric-card">
              <span class="muted">Total Songs</span>
              <strong><?= count($tracks) ?></strong>
            </div>
            <div class="metric-card">
              <span class="muted">Featured Songs</span>
              <strong><?= $featuredCount ?></strong>
            </div>
            <div class="metric-card">
              <span class="muted">Artists</span>
              <strong><?= count($artists) ?></strong>
            </div>
            <div class="metric-card">
              <span class="muted">Storage Used</span>
              <strong><?= e(formatBytes($storageUsed)) ?></strong>
            </div>
          </div>
          <p class="muted">Last uploaded: <?= e((string) ($lastTrack['title'] ?? 'No songs uploaded yet')) ?></p>
        </section>

        <section class="panel" id="website-settings">
          <h1>Website Settings</h1>
          <p class="muted">Control homepage text, social links, and catalog layout.</p>
          <form class="grid" method="post">
            <input type="hidden" name="action" value="save_site">
            <label>
              Site Title
              <input type="text" name="siteTitle" value="<?= e((string) ($site['title'] ?? 'SG Production')) ?>">
            </label>
            <label>
              Tagline
              <input type="text" name="tagline" value="<?= e((string) ($site['tagline'] ?? 'Original music • direct download • no barriers')) ?>">
            </label>
            <label>
              YouTube Heading
              <input type="text" name="youtubeHeading" value="<?= e((string) ($site['youtubeHeading'] ?? 'Subscribe on YouTube')) ?>">
            </label>
            <label>
              Contact Email
              <input type="email" name="contactEmail" value="<?= e((string) ($site['contactEmail'] ?? 'bookings@sgproduction.example')) ?>">
            </label>
            <label class="full">
              YouTube Text
              <input type="text" name="youtubeText" value="<?= e((string) ($site['youtubeText'] ?? defaultSettings()['site']['youtubeText'])) ?>">
            </label>
            <label>
              Instagram URL
              <input type="url" name="instagram" value="<?= e((string) ($links['instagram'] ?? '')) ?>">
            </label>
            <label>
              Spotify URL
              <input type="url" name="spotify" value="<?= e((string) ($links['spotify'] ?? '')) ?>">
            </label>
            <label>
              Apple Music URL
              <input type="url" name="appleMusic" value="<?= e((string) ($links['appleMusic'] ?? '')) ?>">
            </label>
            <label>
              YouTube URL
              <input type="url" name="youtube" value="<?= e((string) ($links['youtube'] ?? '')) ?>">
            </label>
            <label>
              Latest Count
              <input type="number" name="latestCount" value="<?= e((string) ($catalog['latestCount'] ?? 5)) ?>" min="1" max="12">
            </label>
            <label>
              Songs Per Page
              <input type="number" name="tracksPerPage" value="<?= e((string) ($catalog['tracksPerPage'] ?? 15)) ?>" min="5" max="50">
            </label>
            <label>
              Demo Page Count
              <input type="number" name="paginationDemoPages" value="<?= e((string) ($catalog['paginationDemoPages'] ?? 12)) ?>" min="1" max="40">
            </label>
            <div class="full">
              <button class="button primary" type="submit">Save Website Settings</button>
            </div>
          </form>
        </section>

        <section class="panel">
          <h1>Upload New Song</h1>
          <p class="muted">Add a cover, an MP3/WAV preview for playback, and a separate WAV download URL.</p>
          <form class="grid" method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="upload">
            <label>
              Song Title
              <input type="text" name="title" placeholder="Midnight Echo" required>
            </label>
            <label>
              Artist
              <input type="text" name="artist" value="SG Production" required>
            </label>
            <label>
              Artist Profile
              <select name="artistId">
                <?php foreach ($artists as $artistOption): ?>
                  <?php if (is_array($artistOption)): ?>
                    <option value="<?= e((string) ($artistOption['id'] ?? '')) ?>"><?= e((string) ($artistOption['name'] ?? 'Artist')) ?></option>
                  <?php endif; ?>
                <?php endforeach; ?>
              </select>
            </label>
            <label>
              Genre
              <select name="genre">
                <option>Soundcheck</option>
                <option>Marathi</option>
                <option>Hindi</option>
                <option>Original Mix</option>
              </select>
            </label>
            <label>
              Duration
              <input id="durationInput" type="hidden" name="duration" value="">
              <span class="duration-status" id="durationStatus">Select a song file to detect duration</span>
            </label>
            <label>
              BPM
              <input type="number" name="bpm" value="124" min="40" max="240">
            </label>
            <label>
              Wave Style
              <select name="wave">
                <option value="sine">Sine</option>
                <option value="triangle">Triangle</option>
                <option value="sawtooth">Sawtooth</option>
                <option value="square">Square</option>
              </select>
            </label>
            <label>
              Cover Image
              <input type="file" name="cover" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required>
            </label>
            <label>
              Preview Song File
              <input id="audioInput" type="file" name="audio" accept=".wav,.mp3,audio/wav,audio/mpeg" required>
            </label>
            <label class="full">
              WAV Download URL
              <input type="url" name="downloadUrl" placeholder="https://example.com/downloads/nagin-theme.wav" required>
            </label>
            <label class="check-row full">
              <input type="checkbox" name="isNew" checked>
              Mark as new release
            </label>
            <label class="check-row full">
              <input type="checkbox" name="isFeatured" checked>
              Show in Latest Releases
            </label>
            <div class="full">
              <button class="button primary" type="submit">Upload Song</button>
            </div>
          </form>
        </section>

        <section class="panel" id="global-settings">
          <h1>Global Settings</h1>
          <p class="muted">Advertisement controls for the single song page.</p>
          <h2 style="margin-top: 20px;">Advertisement</h2>
          <p class="muted">Use a vertical 9:16 image or video. Recommended size is 1080 × 1920. Videos autoplay muted and loop without controls.</p>
          <?php
            $advertising = is_array($settings['advertising'] ?? null) ? $settings['advertising'] : [];
            $adMediaUrl = (string) ($advertising['mediaUrl'] ?? '');
            $adMediaType = (string) ($advertising['mediaType'] ?? '');
            $adEnabled = !empty($advertising['enabled']);
          ?>
          <?php if ($adMediaUrl !== ''): ?>
            <div class="ad-preview">
              <div class="ad-preview-media">
                <?php if ($adMediaType === 'video'): ?>
                  <video src="<?= e($adMediaUrl) ?>" muted playsinline autoplay loop></video>
                <?php else: ?>
                  <img src="<?= e($adMediaUrl) ?>" alt="Current advertising media">
                <?php endif; ?>
              </div>
              <div>
                <strong><?= $adEnabled ? 'Advertisement is on' : 'Advertisement is off' ?></strong>
                <p class="muted"><?= e($adMediaUrl) ?></p>
              </div>
            </div>
          <?php endif; ?>
          <form class="grid" method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="save_ad">
            <label class="full">
              Advertising Media
              <input type="file" name="adMedia" accept=".jpg,.jpeg,.png,.webp,.mp4,.webm,.mov,image/jpeg,image/png,image/webp,video/mp4,video/webm,video/quicktime">
            </label>
            <label class="full">
              Advertisement Click URL
              <input type="url" name="adLinkUrl" value="<?= e((string) ($advertising['linkUrl'] ?? '')) ?>" placeholder="https://example.com">
            </label>
            <label class="check-row full">
              <input type="checkbox" name="adEnabled" <?= $adEnabled ? 'checked' : '' ?>>
              Show advertisement on single song pages
            </label>
            <div class="full">
              <button class="button primary" type="submit">Save Advertising</button>
            </div>
          </form>
        </section>

        <section class="panel" id="artists">
          <h1>Artist Management</h1>
          <p class="muted">Add or edit artist pages and assign genres to each profile.</p>
          <form class="grid" method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="save_artist">
            <label>
              Artist Name
              <input type="text" name="artistName" placeholder="SG Production" required>
            </label>
            <label>
              Artist Style
              <input type="text" name="artistStyle" placeholder="Original Mix">
            </label>
            <label>
              Release Year
              <input type="text" name="artistYear" value="<?= e(date('Y')) ?>">
            </label>
            <label>
              Artist Image
              <input type="file" name="artistImage" accept=".jpg,.jpeg,.png,.webp,.svg,image/jpeg,image/png,image/webp,image/svg+xml">
            </label>
            <label class="full">
              Genres Assigned
              <input type="text" name="artistGenres" value="Original Mix, Marathi, Soundcheck, Hindi">
            </label>
            <div class="full">
              <button class="button primary" type="submit">Add Artist</button>
            </div>
          </form>

          <div class="artist-admin-list">
            <?php foreach ($artists as $artist): ?>
              <?php if (!is_array($artist)) { continue; } ?>
              <?php
                $artistId = (string) ($artist['id'] ?? '');
                $artistGenres = implode(', ', array_map('strval', is_array($artist['trackGenres'] ?? null) ? $artist['trackGenres'] : []));
              ?>
              <article class="artist-admin-card" id="artist-<?= e($artistId) ?>">
                <div class="artist-admin-head">
                  <img src="<?= e((string) ($artist['image'] ?? 'assets/artist-photo-1.svg')) ?>" alt="">
                  <div>
                    <strong><?= e((string) ($artist['name'] ?? 'Artist')) ?></strong>
                    <span class="muted"><?= e((string) ($artist['style'] ?? 'Original Mix')) ?></span>
                  </div>
                </div>
                <details class="editor">
                  <summary>Edit artist</summary>
                  <form class="grid" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="save_artist">
                    <input type="hidden" name="artistId" value="<?= e($artistId) ?>">
                    <label>
                      Artist Name
                      <input type="text" name="artistName" value="<?= e((string) ($artist['name'] ?? '')) ?>" required>
                    </label>
                    <label>
                      Artist Style
                      <input type="text" name="artistStyle" value="<?= e((string) ($artist['style'] ?? '')) ?>">
                    </label>
                    <label>
                      Release Year
                      <input type="text" name="artistYear" value="<?= e((string) ($artist['year'] ?? date('Y'))) ?>">
                    </label>
                    <label>
                      Replace Image
                      <input type="file" name="artistImage" accept=".jpg,.jpeg,.png,.webp,.svg,image/jpeg,image/png,image/webp,image/svg+xml">
                    </label>
                    <label class="full">
                      Genres Assigned
                      <input type="text" name="artistGenres" value="<?= e($artistGenres) ?>">
                    </label>
                    <div class="full">
                      <button class="button primary" type="submit">Save Artist</button>
                    </div>
                  </form>
                  <form class="mini-form" method="post" onsubmit="return confirm('Delete this artist?');">
                    <input type="hidden" name="action" value="delete_artist">
                    <input type="hidden" name="artistId" value="<?= e($artistId) ?>">
                    <button class="button danger" type="submit">Delete Artist</button>
                  </form>
                </details>
              </article>
            <?php endforeach; ?>
          </div>
        </section>

        <section class="panel" id="uploaded-songs">
          <h2>Uploaded Songs</h2>
          <?php if ($tracks === []): ?>
            <p class="muted">No uploaded songs yet.</p>
          <?php else: ?>
            <div class="track-list">
              <?php foreach ($tracks as $trackIndex => $track): ?>
                <?php if (!is_array($track)) { continue; } ?>
                <?php
                  $trackId = (string) ($track['id'] ?? '');
                  $trackArtistId = (string) ($track['artistId'] ?? 'sg-production');
                ?>
                <div class="track-row" id="track-<?= e($trackId) ?>">
                  <img src="<?= e((string) ($track['cover'] ?? 'assets/cover-1.jpg')) ?>" alt="">
                  <div>
                    <strong><?= e((string) ($track['title'] ?? 'Untitled Track')) ?></strong>
                    <span><?= e((string) ($track['artist'] ?? 'SG Production')) ?> · <?= e((string) ($track['genre'] ?? 'Soundcheck')) ?> · <?= e((string) ($track['duration'] ?? '0:0')) ?><?= !empty($track['isFeatured'] ?? $track['isNew'] ?? false) ? ' · Featured' : '' ?></span>
                  </div>
                  <div class="track-actions">
                    <form class="mini-form" method="post">
                      <input type="hidden" name="action" value="move_track">
                      <input type="hidden" name="trackId" value="<?= e($trackId) ?>">
                      <input type="hidden" name="direction" value="up">
                      <button class="button" type="submit" <?= $trackIndex === 0 ? 'disabled' : '' ?>>Up</button>
                    </form>
                    <form class="mini-form" method="post">
                      <input type="hidden" name="action" value="move_track">
                      <input type="hidden" name="trackId" value="<?= e($trackId) ?>">
                      <input type="hidden" name="direction" value="down">
                      <button class="button" type="submit" <?= $trackIndex === count($tracks) - 1 ? 'disabled' : '' ?>>Down</button>
                    </form>
                    <?php if (!empty($track['previewUrl'])): ?>
                      <a class="button" href="<?= e((string) $track['previewUrl']) ?>">Preview</a>
                    <?php elseif (!empty($track['downloadUrl']) && !isHttpUrl((string) $track['downloadUrl'])): ?>
                      <a class="button" href="<?= e((string) $track['downloadUrl']) ?>">Preview</a>
                    <?php endif; ?>
                    <?php if (!empty($track['downloadUrl'])): ?>
                      <a class="button" href="<?= e((string) $track['downloadUrl']) ?>" target="_blank" rel="noreferrer">Download</a>
                    <?php endif; ?>
                  </div>
                  <details class="editor">
                    <summary>Edit song</summary>
                    <form class="grid" method="post" enctype="multipart/form-data">
                      <input type="hidden" name="action" value="update_track">
                      <input type="hidden" name="trackId" value="<?= e($trackId) ?>">
                      <label>
                        Song Title
                        <input type="text" name="title" value="<?= e((string) ($track['title'] ?? '')) ?>" required>
                      </label>
                      <label>
                        Artist
                        <input type="text" name="artist" value="<?= e((string) ($track['artist'] ?? 'SG Production')) ?>" required>
                      </label>
                      <label>
                        Artist Profile
                        <select name="artistId">
                          <?php foreach ($artists as $artistOption): ?>
                            <?php if (is_array($artistOption)): ?>
                              <?php $optionId = (string) ($artistOption['id'] ?? ''); ?>
                              <option value="<?= e($optionId) ?>" <?= $optionId === $trackArtistId ? 'selected' : '' ?>><?= e((string) ($artistOption['name'] ?? 'Artist')) ?></option>
                            <?php endif; ?>
                          <?php endforeach; ?>
                        </select>
                      </label>
                      <label>
                        Genre
                        <select name="genre">
                          <?php foreach (['Soundcheck', 'Marathi', 'Hindi', 'Original Mix'] as $genreOption): ?>
                            <option <?= ((string) ($track['genre'] ?? '')) === $genreOption ? 'selected' : '' ?>><?= e($genreOption) ?></option>
                          <?php endforeach; ?>
                        </select>
                      </label>
                      <label>
                        Duration
                        <input type="text" name="duration" value="<?= e((string) ($track['duration'] ?? '0:0')) ?>">
                      </label>
                      <label>
                        BPM
                        <input type="number" name="bpm" value="<?= e((string) ($track['bpm'] ?? 124)) ?>" min="40" max="240">
                      </label>
                      <label>
                        Wave Style
                        <select name="wave">
                          <?php foreach (['sine' => 'Sine', 'triangle' => 'Triangle', 'sawtooth' => 'Sawtooth', 'square' => 'Square'] as $waveValue => $waveLabel): ?>
                            <option value="<?= e($waveValue) ?>" <?= ((string) ($track['wave'] ?? 'sine')) === $waveValue ? 'selected' : '' ?>><?= e($waveLabel) ?></option>
                          <?php endforeach; ?>
                        </select>
                      </label>
                      <label>
                        Replace Cover
                        <input type="file" name="cover" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                      </label>
                      <label>
                        Replace Preview File
                        <input type="file" name="audio" accept=".wav,.mp3,audio/wav,audio/mpeg">
                      </label>
                      <label class="full">
                        WAV Download URL
                        <input type="url" name="downloadUrl" value="<?= e((string) ($track['downloadUrl'] ?? '')) ?>" required>
                      </label>
                      <label class="check-row full">
                        <input type="checkbox" name="isNew" <?= !empty($track['isNew']) ? 'checked' : '' ?>>
                        Mark as new release
                      </label>
                      <label class="check-row full">
                        <input type="checkbox" name="isFeatured" <?= !empty($track['isFeatured'] ?? $track['isNew'] ?? false) ? 'checked' : '' ?>>
                        Show in Latest Releases
                      </label>
                      <div class="full">
                        <button class="button primary" type="submit">Save Song</button>
                      </div>
                    </form>
                    <form class="mini-form" method="post" onsubmit="return confirm('Delete this song?');">
                      <input type="hidden" name="action" value="delete_track">
                      <input type="hidden" name="trackId" value="<?= e($trackId) ?>">
                      <label class="check-row">
                        <input type="checkbox" name="deleteFiles" checked>
                        Delete files
                      </label>
                      <button class="button danger" type="submit">Delete Song</button>
                    </form>
                  </details>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>
      <?php endif; ?>
    </main>
    <script>
      const audioInput = document.querySelector("#audioInput");
      const durationInput = document.querySelector("#durationInput");
      const durationStatus = document.querySelector("#durationStatus");

      function formatDuration(seconds) {
        const totalSeconds = Math.max(0, Math.round(seconds));
        const minutes = Math.floor(totalSeconds / 60);
        const remainingSeconds = totalSeconds % 60;

        return `${minutes}:${remainingSeconds}`;
      }

      if (audioInput && durationInput && durationStatus) {
        audioInput.addEventListener("change", () => {
          const file = audioInput.files && audioInput.files[0];

          durationInput.value = "";
          durationStatus.textContent = "Select a song file to detect duration";

          if (!file) {
            return;
          }

          const audio = document.createElement("audio");
          const objectUrl = URL.createObjectURL(file);

          durationStatus.textContent = "Detecting duration...";
          audio.preload = "metadata";
          audio.src = objectUrl;

          audio.addEventListener("loadedmetadata", () => {
            URL.revokeObjectURL(objectUrl);

            if (Number.isFinite(audio.duration) && audio.duration > 0) {
              const duration = formatDuration(audio.duration);
              durationInput.value = duration;
              durationStatus.textContent = duration;
            } else {
              durationStatus.textContent = "Duration will be detected after upload";
            }
          });

          audio.addEventListener("error", () => {
            URL.revokeObjectURL(objectUrl);
            durationStatus.textContent = "Duration will be detected after upload";
          });
        });
      }
    </script>
  </body>
</html>
