<?php
// CLI script to replace photos in existing Kirby adventure pages.
// Fixes two bugs from the original import:
//   1. Cover images were prepended by the exporter (wrong image content in early slots)
//   2. Sort values were assigned in alphabetical file order (0,1,10,11...) not numeric (0,1,2,3...)
//
// Only photo files and their .txt sidecars are replaced.
// adventure.txt, GPX files, and GPX sidecars are left untouched.
//
// Usage:
//   php tools/reimport-photos.php --config tools/import-config.json [--dry-run]
//   php tools/reimport-photos.php --config tools/import-config.json --dry-run 1349272144
//   php tools/reimport-photos.php --tours-root /path/to/tours 1349272144

if (php_sapi_name() !== 'cli') {
    echo "This script must be run from the command line.\n";
    exit(1);
}

// ---------------------------------------------------------------------------
// Argument parsing
// ---------------------------------------------------------------------------

function parseArgs(array $argv): array
{
    $opts = [
        'config'    => null,
        'toursRoot' => null,
        'dryRun'    => false,
        'ids'       => [],
    ];

    $i = 1;
    while ($i < count($argv)) {
        $arg = $argv[$i];
        if ($arg === '--config' && isset($argv[$i + 1])) {
            $opts['config'] = $argv[++$i];
        } elseif ($arg === '--tours-root' && isset($argv[$i + 1])) {
            $opts['toursRoot'] = $argv[++$i];
        } elseif ($arg === '--dry-run') {
            $opts['dryRun'] = true;
        } elseif (ctype_digit($arg)) {
            $opts['ids'][] = $arg;
        } else {
            echo "Unknown argument: $arg\n";
            exit(1);
        }
        $i++;
    }

    return $opts;
}

// ---------------------------------------------------------------------------
// Config loading (identical to import-adventures.php)
// ---------------------------------------------------------------------------

function loadConfig(?string $configPath, array $cliIds, ?string $cliToursRoot): array
{
    $toursRoot = $cliToursRoot;
    $imports   = [];

    if ($configPath !== null) {
        if (!is_file($configPath)) {
            echo "Config file not found: $configPath\n";
            exit(1);
        }
        $json = @file_get_contents($configPath);
        $data = @json_decode($json, true);
        if (!is_array($data)) {
            echo "Failed to parse config file: $configPath\n";
            exit(1);
        }
        if (!empty($data['toursRoot']) && $toursRoot === null) {
            $toursRoot = $data['toursRoot'];
        }
        foreach ($data['imports'] ?? [] as $entry) {
            if (is_string($entry)) {
                $imports[] = [$entry];
            } elseif (is_array($entry) && !empty($entry['ids'])) {
                $imports[] = array_map('strval', $entry['ids']);
            } else {
                echo "Warning: unrecognised import entry in config, skipping.\n";
            }
        }
    }

    foreach ($cliIds as $id) {
        $imports[] = [$id];
    }

    if (empty($imports)) {
        echo "No tour IDs specified. Provide --config or pass IDs as arguments.\n";
        exit(0);
    }

    if ($toursRoot === null) {
        echo "No toursRoot defined. Use --tours-root or set it in the config file.\n";
        exit(1);
    }

    $toursRoot = rtrim($toursRoot, DIRECTORY_SEPARATOR);

    if (!is_dir($toursRoot)) {
        echo "Tours root directory not found: $toursRoot\n";
        exit(1);
    }

    return ['toursRoot' => $toursRoot, 'imports' => $imports];
}

// ---------------------------------------------------------------------------
// Helpers (identical to import-adventures.php)
// ---------------------------------------------------------------------------

function findTourFolder(string $toursRoot, string $id): ?string
{
    $matches = glob($toursRoot . DIRECTORY_SEPARATOR . $id . ' *', GLOB_ONLYDIR);
    if (empty($matches)) {
        $matches = glob($toursRoot . DIRECTORY_SEPARATOR . $id, GLOB_ONLYDIR);
    }
    return !empty($matches) ? $matches[0] : null;
}

function slugify(string $text): string
{
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('~[^\\pL0-9]+~u', '-', $text);
    $text = trim($text, '-');
    $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text) ?: $text;
    $text = preg_replace('~[^-a-z0-9]+~', '', $text);
    $text = preg_replace('~-+~', '-', $text);
    return $text ?: 'untitled';
}

function parseTitleAndDateFromGpx(string $gpxPath): array
{
    $title = null;
    $date  = date('Y-m-d');

    $gpx = @simplexml_load_file($gpxPath);
    if ($gpx === false) {
        return [$title, $date];
    }

    $gpx->registerXPathNamespace('gpx', 'http://www.topografix.com/GPX/1/1');

    $names = $gpx->xpath('//gpx:trk/gpx:name');
    if (empty($names)) {
        $names = $gpx->xpath('//trk/name');
    }
    if (!empty($names)) {
        $val = trim((string) $names[0]);
        if ($val !== '') {
            $title = $val;
        }
    }

    $times = $gpx->xpath('//gpx:trk/gpx:trkseg/gpx:trkpt/gpx:time');
    if (empty($times)) {
        $times = $gpx->xpath('//trk/trkseg/trkpt/time');
    }
    if (!empty($times)) {
        $t  = trim((string) $times[0]);
        $dt = DateTime::createFromFormat(DateTime::ATOM, $t) ?: new DateTime($t);
        if ($dt) {
            $date = $dt->format('Y-m-d');
        }
    }

    return [$title, $date];
}

// ---------------------------------------------------------------------------
// Find the existing Kirby content folder for a group of tour IDs
// ---------------------------------------------------------------------------

function findDestFolder(array $ids, string $toursRoot, string $destRoot): ?string
{
    // Derive the slug by reading the GPX title from the first tour's folder,
    // exactly as import-adventures.php did when it created the folder.
    $firstFolder = findTourFolder($toursRoot, $ids[0]);
    if ($firstFolder === null) {
        return null;
    }

    $gpxFiles = array_merge(
        glob($firstFolder . DIRECTORY_SEPARATOR . '*.gpx') ?: [],
        glob($firstFolder . DIRECTORY_SEPARATOR . '*.GPX') ?: []
    );
    if (empty($gpxFiles)) {
        return null;
    }

    [$title] = parseTitleAndDateFromGpx($gpxFiles[0]);
    if (empty($title)) {
        $title = basename($firstFolder);
    }

    $slug    = slugify($title);
    $matches = glob($destRoot . DIRECTORY_SEPARATOR . '*_' . $slug, GLOB_ONLYDIR);

    return !empty($matches) ? $matches[0] : null;
}

// ---------------------------------------------------------------------------
// Core: replace photos in one destination folder
// ---------------------------------------------------------------------------

function reimportPhotos(array $ids, string $toursRoot, string $destFolder, bool $dryRun): bool
{
    // Collect photos and metadata from all tours in the group, sorted numerically
    $allPhotos  = []; // destName => srcPath, in correct numeric order
    $allMetaMap = []; // destName => metadata entry

    foreach ($ids as $id) {
        $folder = findTourFolder($toursRoot, $id);
        if ($folder === null) {
            echo "  - Error: no folder found for tour ID $id in $toursRoot\n";
            return false;
        }

        $images = array_merge(
            glob($folder . DIRECTORY_SEPARATOR . '*.jpg')  ?: [],
            glob($folder . DIRECTORY_SEPARATOR . '*.JPG')  ?: [],
            glob($folder . DIRECTORY_SEPARATOR . '*.jpeg') ?: [],
            glob($folder . DIRECTORY_SEPARATOR . '*.JPEG') ?: [],
            glob($folder . DIRECTORY_SEPARATOR . '*.png')  ?: [],
            glob($folder . DIRECTORY_SEPARATOR . '*.PNG')  ?: []
        );

        // Sort numerically by filename so Sort values follow 0,1,2,3... not 0,1,10,11...
        usort($images, function (string $a, string $b): int {
            $na = (int) pathinfo($a, PATHINFO_FILENAME);
            $nb = (int) pathinfo($b, PATHINFO_FILENAME);
            return $na - $nb;
        });

        // Load metadata keyed by original filename
        $jsonFiles  = array_merge(
            glob($folder . DIRECTORY_SEPARATOR . '*.json') ?: [],
            glob($folder . DIRECTORY_SEPARATOR . '*.JSON') ?: []
        );
        $sourceMeta = [];
        if (!empty($jsonFiles)) {
            $json = @file_get_contents($jsonFiles[0]);
            $data = @json_decode($json, true);
            if (is_array($data)) {
                foreach ($data as $entry) {
                    if (isset($entry['filename'])) {
                        $sourceMeta[$entry['filename']] = $entry;
                    }
                }
            }
        }

        foreach ($images as $imgPath) {
            $origName = basename($imgPath);
            $destName = $id . '_' . $origName;
            $allPhotos[$destName] = $imgPath;
            if (isset($sourceMeta[$origName])) {
                $entry             = $sourceMeta[$origName];
                $entry['filename'] = $destName;
                $allMetaMap[$destName] = $entry;
            }
        }
    }

    $photoCount = count($allPhotos);
    echo "  - Photos to replace: $photoCount\n";

    if ($dryRun) {
        echo "  - [dry-run] Would delete existing photos and sidecars from: " . basename($destFolder) . "\n";
        echo "  - [dry-run] Would copy $photoCount photos with corrected Sort order\n";
        return true;
    }

    // Delete existing photo files and their sidecars
    $imageExts = ['jpg', 'JPG', 'jpeg', 'JPEG', 'png', 'PNG'];
    $deleted   = 0;
    foreach ($imageExts as $ext) {
        foreach (glob($destFolder . DIRECTORY_SEPARATOR . '*.' . $ext) ?: [] as $f) {
            unlink($f);
            if (file_exists($f . '.txt')) {
                unlink($f . '.txt');
            }
            $deleted++;
        }
    }
    echo "  - Deleted: $deleted existing photo(s) and their sidecars\n";

    // Copy new photos
    $copiedPhotos = [];
    foreach ($allPhotos as $destName => $srcPath) {
        $dest = $destFolder . DIRECTORY_SEPARATOR . $destName;
        if (copy($srcPath, $dest)) {
            $copiedPhotos[] = $destName;
        } else {
            echo "  - Warning: failed to copy $destName\n";
        }
    }
    echo "  - Copied: " . count($copiedPhotos) . " photo(s)\n";

    // Write sidecars — $copiedPhotos is already in numeric order because $allPhotos was built
    // by iterating numerically-sorted $images per tour
    foreach ($copiedPhotos as $photoIdx => $imgName) {
        $meta     = $allMetaMap[$imgName] ?? null;
        $metaFile = $destFolder . DIRECTORY_SEPARATOR . $imgName . '.txt';
        $contents = "Sort: " . ($photoIdx + 1) . "\n\n----\n\n";
        if ($meta) {
            if (isset($meta['created_at'])) $contents .= "Created: " . $meta['created_at'] . "\n\n----\n\n";
            if (isset($meta['lat']))        $contents .= "Latitude: " . $meta['lat'] . "\n\n----\n\n";
            if (isset($meta['lng']))        $contents .= "Longitude: " . $meta['lng'] . "\n\n----\n\n";
        }
        $contents .= "Uuid: import-" . uniqid() . "\n";
        file_put_contents($metaFile, $contents);
    }

    return true;
}

// ---------------------------------------------------------------------------
// Main
// ---------------------------------------------------------------------------

$args      = parseArgs($argv);
$config    = loadConfig($args['config'], $args['ids'], $args['toursRoot']);
$toursRoot = $config['toursRoot'];
$imports   = $config['imports'];
$dryRun    = $args['dryRun'];

$destRoot = __DIR__ . '/../content/1_adventures';
if (!is_dir($destRoot)) {
    echo "Destination root not found: $destRoot\n";
    exit(1);
}

if ($dryRun) {
    echo "*** DRY RUN — no files will be changed ***\n\n";
}

echo "Found " . count($imports) . " import group(s) to process.\n\n";

$successCount = 0;
$skipCount    = 0;

foreach ($imports as $groupIdx => $ids) {
    $label = implode('+', $ids);
    echo "[" . ($groupIdx + 1) . "/" . count($imports) . "] Group: $label\n";

    $destFolder = findDestFolder($ids, $toursRoot, $destRoot);
    if ($destFolder === null) {
        echo "  - Skipping: no matching content folder found (not yet imported, or title mismatch)\n\n";
        $skipCount++;
        continue;
    }

    echo "  - Found: " . basename($destFolder) . "\n";

    $ok = reimportPhotos($ids, $toursRoot, $destFolder, $dryRun);
    if ($ok) {
        $successCount++;
    }

    echo "\n";
}

$verb = $dryRun ? 'Would process' : 'Processed';
echo "Done. $verb: $successCount group(s), skipped: $skipCount group(s).\n";
if (!$dryRun) {
    echo "Clear the Kirby media cache if thumbnails look stale: rm -rf media/\n";
}
