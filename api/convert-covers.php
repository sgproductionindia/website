<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json');

if (empty($_SESSION['sg_admin'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Admin login required.']);
    exit;
}

$rootDir = dirname(__DIR__);
$tracksFile = $rootDir . '/data/tracks.json';
$artistsFile = $rootDir . '/data/artists.json';

function readJsonList(string $file): array
{
    $items = file_exists($file) ? json_decode((string) file_get_contents($file), true) : [];
    return is_array($items) ? $items : [];
}

function writeJsonList(string $file, array $items): void
{
    file_put_contents($file, json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n", LOCK_EX);
}

function webPathToFilePathForConversion(string $webPath, string $rootDir): ?string
{
    $webPath = trim($webPath);

    if ($webPath === '' || preg_match('/^https?:\/\//i', $webPath)) {
        return null;
    }

    $filePath = rtrim($rootDir, '/') . '/' . ltrim($webPath, '/');
    return is_file($filePath) ? $filePath : null;
}

function filePathToWebPathForConversion(string $filePath, string $rootDir, bool $leadingSlash): string
{
    $relativePath = ltrim(str_replace(rtrim($rootDir, '/'), '', $filePath), '/');
    return ($leadingSlash ? '/' : '') . $relativePath;
}

function convertToWebpForMigration(string $webPath, string $rootDir, int $quality = 82): ?string
{
    if (!function_exists('imagewebp')) {
        error_log('WebP conversion skipped: GD WebP support is not available.');
        return null;
    }

    $sourcePath = webPathToFilePathForConversion($webPath, $rootDir);

    if ($sourcePath === null) {
        return null;
    }

    $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));

    if (!in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
        return null;
    }

    $image = null;

    try {
        if (in_array($extension, ['jpg', 'jpeg'], true) && function_exists('imagecreatefromjpeg')) {
            $image = @imagecreatefromjpeg($sourcePath);
        } elseif ($extension === 'png' && function_exists('imagecreatefrompng')) {
            $image = @imagecreatefrompng($sourcePath);

            if ($image !== false && function_exists('imagepalettetotruecolor')) {
                imagepalettetotruecolor($image);
            }

            if ($image !== false) {
                imagealphablending($image, true);
                imagesavealpha($image, true);
            }
        }

        if (!$image) {
            error_log('WebP conversion skipped: unsupported or unreadable image ' . $webPath);
            return null;
        }

        $webpPath = preg_replace('/\.(jpe?g|png)$/i', '.webp', $sourcePath) ?? ($sourcePath . '.webp');

        if (@imagewebp($image, $webpPath, $quality) !== true) {
            error_log('WebP conversion failed for ' . $webPath);
            return null;
        }

        @chmod($webpPath, 0664);

        return filePathToWebPathForConversion($webpPath, $rootDir, str_starts_with($webPath, '/'));
    } finally {
        if ($image instanceof GdImage) {
            imagedestroy($image);
        }
    }
}

function migrateImageList(array &$items, string $sourceField, string $webpField, string $rootDir): array
{
    $summary = ['converted' => 0, 'skipped' => 0, 'failed' => 0];

    foreach ($items as &$item) {
        if (!is_array($item)) {
            $summary['skipped']++;
            continue;
        }

        $source = (string) ($item[$sourceField] ?? '');

        if ($source === '') {
            $summary['skipped']++;
            continue;
        }

        $existingWebp = (string) ($item[$webpField] ?? '');
        $existingPath = $existingWebp !== '' ? webPathToFilePathForConversion($existingWebp, $rootDir) : null;

        if ($existingPath !== null) {
            $summary['skipped']++;
            continue;
        }

        $extension = strtolower(pathinfo(parse_url($source, PHP_URL_PATH) ?: $source, PATHINFO_EXTENSION));

        if (!in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
            $summary['skipped']++;
            continue;
        }

        $webpPath = convertToWebpForMigration($source, $rootDir);

        if ($webpPath !== null) {
            $item[$webpField] = $webpPath;
            $summary['converted']++;
        } else {
            $summary['failed']++;
        }
    }
    unset($item);

    return $summary;
}

$tracks = readJsonList($tracksFile);
$artists = readJsonList($artistsFile);

$trackSummary = migrateImageList($tracks, 'cover', 'coverWebp', $rootDir);
$artistSummary = migrateImageList($artists, 'image', 'imageWebp', $rootDir);

writeJsonList($tracksFile, $tracks);
writeJsonList($artistsFile, $artists);

echo json_encode([
    'ok' => true,
    'converted' => $trackSummary['converted'] + $artistSummary['converted'],
    'skipped' => $trackSummary['skipped'] + $artistSummary['skipped'],
    'failed' => $trackSummary['failed'] + $artistSummary['failed'],
]);
