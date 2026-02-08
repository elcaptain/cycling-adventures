<?php
// CLI script to import folders as Kirby adventure pages.
// Usage: php tools/import-adventures.php /path/to/source

if (php_sapi_name() !== 'cli') {
    echo "This script must be run from the command line.\n";
    exit(1);
}

$argc = $_SERVER['argc'];
$argv = $_SERVER['argv'];

if ($argc < 2) {
    echo "Usage: php tools/import-adventures.php /path/to/source [dest=content/1_adventures]\n";
    exit(1);
}

$source = rtrim($argv[1], DIRECTORY_SEPARATOR);
$destRoot = isset($argv[2]) ? rtrim($argv[2], DIRECTORY_SEPARATOR) : __DIR__ . '/../content/1_adventures';

if (!is_dir($source)) {
    echo "Source directory not found: $source\n";
    exit(1);
}

if (!is_dir($destRoot)) {
    echo "Destination root not found: $destRoot\n";
    exit(1);
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
    $max = 0;
    $items = scandir($destRoot);
    foreach ($items as $item) {
        if (preg_match('/^(\d+)_/', $item, $m)) {
            $num = intval($m[1]);
            if ($num > $max) $max = $num;
        }
    }
    return $max + 1;
}

// Read folders inside source
$folders = array_filter(glob($source . DIRECTORY_SEPARATOR . '*'), 'is_dir');
if (empty($folders)) {
    echo "No folders found in source: $source\n";
    exit(0);
}

echo "Found " . count($folders) . " folders. Starting import...\n";

$prefixBase = nextNumericPrefix($destRoot);
$progress = 0;

foreach ($folders as $folder) {
    $progress++;
    echo "\n[$progress/" . count($folders) . "] Processing: $folder\n";

    try {
        // find gpx
        $gpxFiles = array_merge(glob($folder . DIRECTORY_SEPARATOR . '*.gpx'), glob($folder . DIRECTORY_SEPARATOR . '*.GPX'));
        if (empty($gpxFiles)) {
            echo "  - Skipping: no GPX file found.\n";
            continue;
        }
        $gpxPath = $gpxFiles[0];

        // parse GPX to get track <name> and date from first trackpoint time
        $gpx = @simplexml_load_file($gpxPath);
        $title = null;
        $date = date('Y-m-d');
        if ($gpx !== false) {
            // Register GPX namespace for XPath
            $gpx->registerXPathNamespace('gpx', 'http://www.topografix.com/GPX/1/1');
            
            // Try to get track name (with or without namespace)
            $names = $gpx->xpath('//gpx:trk/gpx:name');
            if (empty($names)) {
                $names = $gpx->xpath('//trk/name');
            }
            if (!empty($names)) {
                $val = trim((string)$names[0]);
                if ($val !== '') {
                    $title = $val;
                }
            }
            
            // Get date from first trackpoint time (with or without namespace)
            $times = $gpx->xpath('//gpx:trk/gpx:trkseg/gpx:trkpt/gpx:time');
            if (empty($times)) {
                $times = $gpx->xpath('//trk/trkseg/trkpt/time');
            }
            if (!empty($times)) {
                $t = trim((string)$times[0]);
                if ($t !== '') {
                    $dt = DateTime::createFromFormat(DateTime::ATOM, $t) ?: new DateTime($t);
                    if ($dt) $date = $dt->format('Y-m-d');
                }
            }
        }

        if (empty($title)) {
            // fallback to folder name
            $title = basename($folder);
        }

        $slug = slugify($title);

        // determine new folder name using prefix
        $prefix = $prefixBase++;
        $newFolderName = $prefix . '_' . $slug;
        $newFolder = rtrim($destRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $newFolderName;

        if (is_dir($newFolder)) {
            echo "  - Destination already exists: $newFolder (skipping)\n";
            continue;
        }

        if (!mkdir($newFolder, 0755, true)) {
            echo "  - Failed to create folder: $newFolder\n";
            continue;
        }

        // copy GPX
        $gpxBasename = basename($gpxPath);
        if (!copy($gpxPath, $newFolder . DIRECTORY_SEPARATOR . $gpxBasename)) {
            echo "  - Warning: failed to copy GPX file.\n";
        } else {
            echo "  - Copied GPX: $gpxBasename\n";
        }

        // copy images (jpg/jpeg/png)
        $images = array_merge(glob($folder . DIRECTORY_SEPARATOR . '*.jpg'), glob($folder . DIRECTORY_SEPARATOR . '*.JPG'), glob($folder . DIRECTORY_SEPARATOR . '*.jpeg'), glob($folder . DIRECTORY_SEPARATOR . '*.png'));
        $copied = [];
        foreach ($images as $img) {
            $bn = basename($img);
            if (copy($img, $newFolder . DIRECTORY_SEPARATOR . $bn)) {
                $copied[] = $bn;
            } else {
                echo "  - Warning: failed to copy image $bn\n";
            }
        }
        echo "  - Images copied: " . count($copied) . "\n";

        // read JSON metadata (first .json file in folder)
        $jsonFiles = array_merge(glob($folder . DIRECTORY_SEPARATOR . '*.json'), glob($folder . DIRECTORY_SEPARATOR . '*.JSON'));
        $metaMap = [];
        if (!empty($jsonFiles)) {
            $jsonPath = $jsonFiles[0];
            $jsonContent = @file_get_contents($jsonPath);
            $data = @json_decode($jsonContent, true);
            if (is_array($data)) {
                foreach ($data as $item) {
                    if (isset($item['filename'])) {
                        $metaMap[$item['filename']] = $item;
                    }
                }
                echo "  - Loaded metadata entries: " . count($metaMap) . " from " . basename($jsonPath) . "\n";
            } else {
                echo "  - Warning: failed to parse JSON metadata: " . basename($jsonPath) . "\n";
            }
        } else {
            echo "  - No JSON metadata file found.\n";
        }

        // create image metadata files next to images
        foreach ($copied as $idx => $imgName) {
            $meta = $metaMap[$imgName] ?? null;
            $metaFile = $newFolder . DIRECTORY_SEPARATOR . $imgName . '.txt';
            $contents = '';
            // optional sort field
            $contents .= "Sort: " . ($idx + 1) . "\n\n----\n\n";
            if ($meta) {
                if (isset($meta['created_at'])) {
                    $contents .= "Created: " . $meta['created_at'] . "\n\n----\n\n";
                }
                if (isset($meta['lat'])) {
                    $contents .= "Latitude: " . $meta['lat'] . "\n\n----\n\n";
                }
                if (isset($meta['lng'])) {
                    $contents .= "Longitude: " . $meta['lng'] . "\n\n----\n\n";
                }
            } else {
                $contents .= "# No additional metadata available\n";
            }
            // add a Uuid placeholder so Kirby recognises the file metadata format
            $contents .= "Uuid: import-" . uniqid() . "\n";
            @file_put_contents($metaFile, $contents);
        }

        // read comments.txt
        $comments = '';
        $commentsPath = $folder . DIRECTORY_SEPARATOR . 'comments.txt';
        if (is_file($commentsPath)) {
            $comments = trim(file_get_contents($commentsPath));
            echo "  - Read comments.txt (" . strlen($comments) . " chars)\n";
        } else {
            echo "  - No comments.txt found.\n";
        }

        // create content file (adventure.txt)
        $contentFile = $newFolder . DIRECTORY_SEPARATOR . 'adventure.txt';
        $layoutBlock = '';
        if ($comments !== '') {
            // indent carefully to match existing Layout format
            $commentEscaped = str_replace("\r\n", "\n", $comments);
            $commentLines = explode("\n", $commentEscaped);
            $quoted = '';
            foreach ($commentLines as $line) {
                $quoted .= "      " . rtrim($line) . "\n";
            }

            $layoutBlock = "Layout:\n-\n  type: text\n  content:\n    text: >\n" . $quoted . "\n----\n\n";
        } else {
            $layoutBlock = "Layout:\n-\n  type: text\n  content:\n    text: >\n      (Imported content)\n\n----\n\n";
        }

        $content = "Title: " . $title . "\n\n----\n\n";
        $content .= "Date: " . $date . "\n\n----\n\n";
        $content .= $layoutBlock;
        $content .= "Uuid: import-" . uniqid() . "\n";

        if (file_put_contents($contentFile, $content) === false) {
            echo "  - Error: failed to write content file.\n";
        } else {
            echo "  - Wrote adventure content: adventure.txt\n";
        }

        echo "  - Import complete for: $title (folder: $newFolderName)\n";

    } catch (Throwable $e) {
        echo "  - Error importing folder: " . $e->getMessage() . "\n";
        continue;
    }

}

echo "\nImport finished. Please run `composer start` and open /panel to verify imported pages.\n";

exit(0);
