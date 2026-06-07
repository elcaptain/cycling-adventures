<?php
// CLI script to import Komoot tours as Kirby adventure pages.
//
// Usage:
//   php tools/import-adventures.php --config tools/import-config.json
//   php tools/import-adventures.php --config tools/import-config.json 654414295 365755270
//   php tools/import-adventures.php --tours-root /path/to/tours 654414295 365755270

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
        'ids'       => [],
    ];

    $i = 1;
    while ($i < count($argv)) {
        $arg = $argv[$i];
        if ($arg === '--config' && isset($argv[$i + 1])) {
            $opts['config'] = $argv[++$i];
        } elseif ($arg === '--tours-root' && isset($argv[$i + 1])) {
            $opts['toursRoot'] = $argv[++$i];
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
// Config loading
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

    // CLI IDs appended as individual single-tour imports
    foreach ($cliIds as $id) {
        $imports[] = [$id];
    }

    if (empty($imports)) {
        echo "No tour IDs to import. Provide --config or pass IDs as arguments.\n";
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
// Duplicate detection
// ---------------------------------------------------------------------------

function loadImportedIds(string $logPath): array
{
    if (!is_file($logPath)) {
        return [];
    }
    $lines = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    return array_map('trim', $lines ?: []);
}

function appendImportedIds(string $logPath, array $ids): void
{
    $lines = implode("\n", $ids) . "\n";
    file_put_contents($logPath, $lines, FILE_APPEND | LOCK_EX);
}

// ---------------------------------------------------------------------------
// Tour folder lookup
// ---------------------------------------------------------------------------

function findTourFolder(string $toursRoot, string $id): ?string
{
    // Folder names start with the numeric ID followed by a space, or the ID alone
    $matches = glob($toursRoot . DIRECTORY_SEPARATOR . $id . ' *', GLOB_ONLYDIR);
    if (empty($matches)) {
        $matches = glob($toursRoot . DIRECTORY_SEPARATOR . $id, GLOB_ONLYDIR);
    }
    return !empty($matches) ? $matches[0] : null;
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function generateBlockId(): string
{
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
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

function nextNumericPrefix(string $destRoot): int
{
    $max   = 0;
    $items = scandir($destRoot);
    foreach ($items as $item) {
        if (preg_match('/^(\d+)_/', $item, $m)) {
            $num = intval($m[1]);
            if ($num > $max) $max = $num;
        }
    }
    return $max + 1;
}

// ---------------------------------------------------------------------------
// GPX parsing
// ---------------------------------------------------------------------------

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
// Core import
// ---------------------------------------------------------------------------

function importGroup(array $ids, string $toursRoot, string $destRoot): bool
{
    // Collect per-tour data in order
    $tourDataList = [];
    $allPhotos    = []; // destName => srcPath
    $allMetaMap   = []; // destName => metadata entry
    $primaryTitle = null;
    $primaryDate  = date('Y-m-d');

    foreach ($ids as $idx => $id) {
        $folder = findTourFolder($toursRoot, $id);
        if ($folder === null) {
            echo "  - Error: no folder found for tour ID $id in $toursRoot\n";
            return false;
        }
        echo "  - Tour $id → " . basename($folder) . "\n";

        // GPX files
        $gpxFiles = array_merge(
            glob($folder . DIRECTORY_SEPARATOR . '*.gpx') ?: [],
            glob($folder . DIRECTORY_SEPARATOR . '*.GPX') ?: []
        );
        if (empty($gpxFiles)) {
            echo "  - Error: no GPX file found in $folder\n";
            return false;
        }

        // Title and date from first tour's first GPX only
        if ($idx === 0) {
            [$primaryTitle, $primaryDate] = parseTitleAndDateFromGpx($gpxFiles[0]);
            if (empty($primaryTitle)) {
                $primaryTitle = basename($folder);
            }
        }

        // Photos — rename with tour ID prefix to avoid collisions
        $images = array_merge(
            glob($folder . DIRECTORY_SEPARATOR . '*.jpg')  ?: [],
            glob($folder . DIRECTORY_SEPARATOR . '*.JPG')  ?: [],
            glob($folder . DIRECTORY_SEPARATOR . '*.jpeg') ?: [],
            glob($folder . DIRECTORY_SEPARATOR . '*.JPEG') ?: [],
            glob($folder . DIRECTORY_SEPARATOR . '*.png')  ?: [],
            glob($folder . DIRECTORY_SEPARATOR . '*.PNG')  ?: []
        );

        // Load metadata for this tour
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
                echo "    Loaded " . count($sourceMeta) . " metadata entries from " . basename($jsonFiles[0]) . "\n";
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

        // Comments
        $commentsText = null;
        $commentsPath = $folder . DIRECTORY_SEPARATOR . 'comments.txt';
        if (is_file($commentsPath)) {
            $text = trim(file_get_contents($commentsPath));
            if ($text !== '') {
                $commentsText = $text;
                echo "    Read comments.txt (" . strlen($text) . " chars)\n";
            }
        }

        $tourDataList[] = [
            'id'       => $id,
            'gpxPaths' => $gpxFiles,
            'comments' => $commentsText,
        ];
    }

    // Create destination folder
    $slug          = slugify($primaryTitle);
    $prefix        = nextNumericPrefix($destRoot);
    $newFolderName = $prefix . '_' . $slug;
    $newFolder     = $destRoot . DIRECTORY_SEPARATOR . $newFolderName;

    if (is_dir($newFolder)) {
        echo "  - Destination already exists: $newFolder (skipping)\n";
        return false;
    }

    if (!mkdir($newFolder, 0755, true)) {
        echo "  - Failed to create folder: $newFolder\n";
        return false;
    }

    // Copy GPX files; create UUID sidecar .txt so daystats blocks can reference them
    $allCopiedGpxNames = []; // flat list for Gpxfile: field
    $tourGpxInfo       = []; // [tIdx => [['name', 'uuid'], ...]]

    foreach ($tourDataList as $tIdx => $td) {
        $tourGpxInfo[$tIdx] = [];
        foreach ($td['gpxPaths'] as $gpxPath) {
            $gpxName = basename($gpxPath);
            $gpxUuid = 'import-' . uniqid();
            if (copy($gpxPath, $newFolder . DIRECTORY_SEPARATOR . $gpxName)) {
                file_put_contents($newFolder . DIRECTORY_SEPARATOR . $gpxName . '.txt', "Uuid: $gpxUuid\n");
                $allCopiedGpxNames[]    = $gpxName;
                $tourGpxInfo[$tIdx][]   = ['name' => $gpxName, 'uuid' => $gpxUuid];
                echo "  - Copied GPX: $gpxName\n";
            } else {
                echo "  - Warning: failed to copy GPX: $gpxName\n";
            }
        }
    }

    // Copy photos with renamed filenames
    $copiedPhotos = [];
    foreach ($allPhotos as $destName => $srcPath) {
        if (copy($srcPath, $newFolder . DIRECTORY_SEPARATOR . $destName)) {
            $copiedPhotos[] = $destName;
        } else {
            echo "  - Warning: failed to copy image $destName\n";
        }
    }
    echo "  - Images copied: " . count($copiedPhotos) . "\n";

    // Write Kirby sidecar .txt files for each photo
    foreach ($copiedPhotos as $photoIdx => $imgName) {
        $meta     = $allMetaMap[$imgName] ?? null;
        $metaFile = $newFolder . DIRECTORY_SEPARATOR . $imgName . '.txt';
        $contents = "Sort: " . ($photoIdx + 1) . "\n\n----\n\n";
        if ($meta) {
            if (isset($meta['created_at'])) $contents .= "Created: " . $meta['created_at'] . "\n\n----\n\n";
            if (isset($meta['lat']))        $contents .= "Latitude: " . $meta['lat'] . "\n\n----\n\n";
            if (isset($meta['lng']))        $contents .= "Longitude: " . $meta['lng'] . "\n\n----\n\n";
        }
        $contents .= "Uuid: import-" . uniqid() . "\n";
        @file_put_contents($metaFile, $contents);
    }

    // Build adventure.txt
    $content  = "Title: " . $primaryTitle . "\n\n----\n\n";
    $content .= "Date: " . $primaryDate . "\n\n----\n\n";

    // Gpxfile field
    if (count($allCopiedGpxNames) === 1) {
        $content .= "Gpxfile: " . $allCopiedGpxNames[0] . "\n\n----\n\n";
    } else {
        $content .= "Gpxfile:\n";
        foreach ($allCopiedGpxNames as $gpxName) {
            $content .= "- " . $gpxName . "\n";
        }
        $content .= "\n----\n\n";
    }

    // Photos field
    if (!empty($copiedPhotos)) {
        $content .= "Photos:\n";
        foreach ($copiedPhotos as $imgName) {
            $content .= "- " . $imgName . "\n";
        }
        $content .= "\n----\n\n";
    }

    // Layout blocks — stored as JSON (Kirby's native format)
    // Multi-tour: daystats block per GPX, then comment segments, repeating per tour.
    // Single-tour: comment segments only (no daystats).
    $layoutBlocks = [];
    $isMultiTour  = count($ids) > 1;

    foreach ($tourDataList as $tIdx => $td) {
        if ($isMultiTour) {
            foreach ($tourGpxInfo[$tIdx] as $gpxInfo) {
                $layoutBlocks[] = [
                    'content'  => [
                        'text'    => 'Day ' . ($tIdx + 1),
                        'level'   => 'h2',
                        'gpxfile' => ['file://' . $gpxInfo['uuid']],
                    ],
                    'id'       => generateBlockId(),
                    'isHidden' => false,
                    'type'     => 'daystats',
                ];
            }
        }

        if ($td['comments'] !== null) {
            $normalized = str_replace("\r\n", "\n", $td['comments']);
            $segments   = preg_split('/^\s*---\s*$/m', $normalized);
            foreach ($segments as $segment) {
                $segment = trim($segment);
                if ($segment !== '') {
                    $layoutBlocks[] = [
                        'content'  => ['text' => $segment],
                        'id'       => generateBlockId(),
                        'isHidden' => false,
                        'type'     => 'text',
                    ];
                }
            }
        }
    }

    if (!empty($layoutBlocks)) {
        $content .= "Layout: " . json_encode($layoutBlocks, JSON_UNESCAPED_UNICODE) . "\n\n----\n\n";
    }

    $content .= "Uuid: import-" . uniqid() . "\n";

    if (file_put_contents($newFolder . DIRECTORY_SEPARATOR . 'adventure.txt', $content) === false) {
        echo "  - Error: failed to write adventure.txt\n";
        return false;
    }

    echo "  - Import complete: $primaryTitle (folder: $newFolderName)\n";
    return true;
}

// ---------------------------------------------------------------------------
// Main
// ---------------------------------------------------------------------------

$args      = parseArgs($argv);
$config    = loadConfig($args['config'], $args['ids'], $args['toursRoot']);
$toursRoot = $config['toursRoot'];
$imports   = $config['imports'];  // array of ID arrays

$destRoot = __DIR__ . '/../content/1_adventures';
if (!is_dir($destRoot)) {
    echo "Destination root not found: $destRoot\n";
    exit(1);
}

$logPath     = __DIR__ . '/imported-tours.log';
$importedIds = loadImportedIds($logPath);
$importedSet = array_flip($importedIds); // for O(1) lookup

echo "Loaded " . count($importedIds) . " already-imported tour IDs from log.\n";
echo "Found " . count($imports) . " import group(s) to process.\n\n";

$successCount = 0;
$skipCount    = 0;

foreach ($imports as $groupIdx => $ids) {
    $label = implode('+', $ids);
    echo "[" . ($groupIdx + 1) . "/" . count($imports) . "] Group: $label\n";

    // Duplicate check
    $alreadyImported = array_filter($ids, fn($id) => isset($importedSet[$id]));

    if (count($alreadyImported) === count($ids)) {
        echo "  - Skipping: all IDs already imported (" . implode(', ', $alreadyImported) . ")\n\n";
        $skipCount++;
        continue;
    }

    if (!empty($alreadyImported)) {
        echo "  - Skipping: partial overlap — some IDs already imported (" . implode(', ', $alreadyImported) . ")\n";
        echo "    Remove them from imported-tours.log to force re-import.\n\n";
        $skipCount++;
        continue;
    }

    $ok = importGroup($ids, $toursRoot, $destRoot);

    if ($ok) {
        appendImportedIds($logPath, $ids);
        foreach ($ids as $id) {
            $importedSet[$id] = true;
        }
        $successCount++;
    }

    echo "\n";
}

echo "Done. Imported: $successCount group(s), skipped: $skipCount group(s).\n";
echo "Open /panel to verify imported pages.\n";

exit(0);
