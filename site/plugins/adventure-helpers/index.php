<?php

/**
 * Adventure Helpers Plugin
 *
 * This plugin provides helper functions for:
 * - Parsing GPX files to extract ride statistics
 * - Reading EXIF data from photos
 * - Matching photos to GPS coordinates based on timestamps
 */

Kirby::plugin('cycling/adventure-helpers', [
    'pageMethods' => [
        /**
         * Get GPX data from the adventure page
         * Returns an array of statistics and track points
         */
        'gpxData' => function() {
            $gpxFile = $this->files()->filterBy('extension', 'gpx')->first();

            if (!$gpxFile) {
                return null;
            }

            return parseGPX($gpxFile->root());
        },

        /**
         * Get photos with EXIF data
         * Returns an array of photos with timestamps and GPS coordinates
         */
        'photosWithExif' => function() {
            $photos = $this->images();
            $photoData = [];

            foreach ($photos as $photo) {
                $exif = getPhotoExif($photo->root());

                $timestamp = null;
                $lat = null;
                $lon = null;

                if ($exif) {
                    $timestamp = $exif['timestamp'] ?? null;
                    $lat = $exif['lat'] ?? null;
                    $lon = $exif['lon'] ?? null;
                }

                // Fallback: if EXIF missing or incomplete, try Kirby file fields (sidecar) like `latitude()`/`longitude()` and `created()`
                if (!$timestamp) {
                    try {
                        $c = $photo->created();
                        if (!empty($c)) {
                            $ts = is_object($c) && method_exists($c, 'toDateTime') ? $c->toDateTime()->getTimestamp() : @strtotime((string)$c);
                            if ($ts !== false) $timestamp = $ts;
                        }
                    } catch (Throwable $e) {
                        // created() may not exist or is empty
                    }
                }

                if (!$lat) {
                    try {
                        $latVal = $photo->latitude();
                        if (!empty($latVal)) {
                            $lat = floatval((string)$latVal);
                        }
                    } catch (Throwable $e) {
                        // latitude() may not exist
                    }
                }

                if (!$lon) {
                    try {
                        $lonVal = $photo->longitude();
                        if (!empty($lonVal)) {
                            $lon = floatval((string)$lonVal);
                        }
                    } catch (Throwable $e) {
                        // longitude() may not exist
                    }
                }

                // Include all photos; matchPhotosToTrack will try all matching sources (EXIF, sidecar, JSON, timestamps)
                $photoData[] = [
                    'file' => $photo,
                    'url' => $photo->url(),
                    'thumb' => $photo->resize(400)->url(),
                    'timestamp' => $timestamp,
                    'lat' => $lat,
                    'lon' => $lon
                ];
            }

            return $photoData;
        },

        /**
         * Get matched photos (photos matched to GPX track points)
         */
        'matchedPhotos' => function() {
            $tracks = $this->gpxDataAll();
            $photos = $this->photosWithExif();

            if (empty($tracks) || empty($photos)) {
                return [];
            }

            $allTrackpoints = [];
            foreach ($tracks as $track) {
                foreach ($track['trackpoints'] as $tp) {
                    $allTrackpoints[] = $tp;
                }
            }

            return matchPhotosToTrack($photos, $allTrackpoints);
        },

        /**
         * Get GPX data for all uploaded GPX files, one entry per file/day
         */
        'gpxDataAll' => function() {
            $gpxFiles = $this->files()->filterBy('extension', 'gpx');
            if ($gpxFiles->count() === 0) {
                return [];
            }
            $colors = ['#2563eb', '#16a34a', '#d97706', '#7c3aed', '#e11d48'];
            $tracks = [];
            $index  = 0;
            foreach ($gpxFiles as $gpxFile) {
                $parsed = parseGPX($gpxFile->root());
                if ($parsed) {
                    $tracks[] = [
                        'index'       => $index,
                        'color'       => $colors[$index % count($colors)],
                        'trackpoints' => $parsed['trackpoints'],
                        'stats'       => $parsed['stats'],
                        'bounds'      => $parsed['bounds'],
                    ];
                    $index++;
                }
            }
            return $tracks;
        },

        /**
         * Get gallery photos sorted chronologically by capture time.
         * Falls back to nearest trackpoint time for GPS-located photos without a timestamp.
         * Photos with no sortable information are appended at the end in original order.
         */
        'galleryPhotosSorted' => function() {
            $galleryFiles = $this->photos()->toFiles();
            if ($galleryFiles->isEmpty()) {
                return [];
            }

            $tracks = $this->gpxDataAll();
            $allTrackpoints = [];
            foreach ($tracks as $track) {
                foreach ($track['trackpoints'] as $tp) {
                    $allTrackpoints[] = $tp;
                }
            }

            $sortKeys = [];
            foreach ($this->photosWithExif() as $photoData) {
                $filename = $photoData['file']->filename();
                $key = $photoData['timestamp'];

                if ($key === null && !empty($photoData['lat']) && !empty($photoData['lon']) && !empty($allTrackpoints)) {
                    $key = findTrackSortKey($photoData['lat'], $photoData['lon'], $allTrackpoints);
                }

                $sortKeys[$filename] = $key;
            }

            $arr = [];
            foreach ($galleryFiles as $image) {
                $arr[] = $image;
            }

            usort($arr, function($a, $b) use ($sortKeys) {
                $aKey = $sortKeys[$a->filename()] ?? null;
                $bKey = $sortKeys[$b->filename()] ?? null;

                if ($aKey === null && $bKey === null) return 0;
                if ($aKey === null) return 1;
                if ($bKey === null) return -1;
                return $aKey <=> $bKey;
            });

            return $arr;
        },

        /**
         * Get aggregated ride statistics across all GPX files
         */
        'gpxAggregatedStats' => function() {
            $tracks = $this->gpxDataAll();
            if (empty($tracks)) {
                return null;
            }
            $totalDistance   = 0;
            $totalElevation  = 0;
            $totalMovingTime = 0;
            $totalDuration   = 0;
            foreach ($tracks as $track) {
                $s = $track['stats'];
                $totalDistance   += $s['distance']      ?? 0;
                $totalElevation  += $s['elevation_gain'] ?? 0;
                $totalMovingTime += $s['moving_time']    ?? 0;
                $totalDuration   += $s['duration']       ?? 0;
            }
            $avgSpeed = ($totalMovingTime > 0)
                ? round($totalDistance / ($totalMovingTime / 3600), 1)
                : null;
            return [
                'distance'              => round($totalDistance, 2),
                'elevation_gain'        => round($totalElevation, 0),
                'moving_time'           => $totalMovingTime,
                'moving_time_formatted' => formatDuration($totalMovingTime),
                'duration'              => $totalDuration,
                'duration_formatted'    => formatDuration($totalDuration),
                'avg_speed'             => $avgSpeed,
                'days'                  => count($tracks),
            ];
        }
    ]
]);

/**
 * Parse GPX file and extract statistics
 *
 * @param string $gpxPath Path to GPX file
 * @return array|null GPX data including statistics and track points
 */
function parseGPX($gpxPath) {
    if (!file_exists($gpxPath)) {
        return null;
    }

    // Load GPX file
    $xml = simplexml_load_file($gpxPath);
    if (!$xml) {
        return null;
    }

    // Register namespaces
    $namespaces = $xml->getNamespaces(true);
    $xml->registerXPathNamespace('gpx', 'http://www.topografix.com/GPX/1/1');

    // Extract track points
    $trackpoints = [];
    $elevations = [];
    $timestamps = [];
    $totalDistance = 0;
    $lastPoint = null;

    // Get all track points
    $trkpts = $xml->xpath('//gpx:trkpt');

    foreach ($trkpts as $point) {
        $lat = (float) $point['lat'];
        $lon = (float) $point['lon'];
        $ele = isset($point->ele) ? (float) $point->ele : null;
        $time = isset($point->time) ? (string) $point->time : null;

        $trackpoint = [
            'lat' => $lat,
            'lon' => $lon,
            'elevation' => $ele,
            'time' => $time ? strtotime($time) : null
        ];

        $trackpoints[] = $trackpoint;

        if ($ele !== null) {
            $elevations[] = $ele;
        }

        if ($time) {
            $timestamps[] = strtotime($time);
        }

        // Calculate distance
        if ($lastPoint) {
            $totalDistance += haversineDistance(
                $lastPoint['lat'],
                $lastPoint['lon'],
                $lat,
                $lon
            );
        }

        $lastPoint = $trackpoint;
    }

    // Calculate statistics
    $stats = [];

    // Distance
    $stats['distance'] = round($totalDistance, 2);

    // Elevation gain
    if (!empty($elevations)) {
        $elevationGain = 0;
        for ($i = 1; $i < count($elevations); $i++) {
            $diff = $elevations[$i] - $elevations[$i - 1];
            if ($diff > 0) {
                $elevationGain += $diff;
            }
        }
        $stats['elevation_gain'] = round($elevationGain, 0);
    }

    // Duration and moving time
    if (count($timestamps) >= 2) {
        $duration = end($timestamps) - reset($timestamps);
        $stats['duration'] = $duration;
        $stats['duration_formatted'] = formatDuration($duration);

        // Calculate moving time: sum segments where speed >= 1 km/h
        $movingTime = 0;
        for ($i = 1; $i < count($trackpoints); $i++) {
            $prev = $trackpoints[$i - 1];
            $curr = $trackpoints[$i];
            if ($prev['time'] === null || $curr['time'] === null) continue;
            $timeDiff = $curr['time'] - $prev['time'];
            if ($timeDiff <= 0) continue;
            $dist = haversineDistance($prev['lat'], $prev['lon'], $curr['lat'], $curr['lon']);
            if (($dist / ($timeDiff / 3600)) >= 1.0) {
                $movingTime += $timeDiff;
            }
        }
        $stats['moving_time'] = $movingTime;
        $stats['moving_time_formatted'] = formatDuration($movingTime);

        // Average speed based on moving time (matches Komoot's display)
        if ($movingTime > 0) {
            $stats['avg_speed'] = round($totalDistance / ($movingTime / 3600), 1);
        }
    }

    // Downsample to max 1500 points (stats already calculated above from full set)
    $maxPoints = 1500;
    if (count($trackpoints) > $maxPoints) {
        $step = (int)ceil(count($trackpoints) / $maxPoints);
        $sampled = [];
        for ($i = 0; $i < count($trackpoints); $i += $step) {
            $sampled[] = $trackpoints[$i];
        }
        $last = end($trackpoints);
        if (end($sampled) !== $last) {
            $sampled[] = $last;
        }
        $trackpoints = $sampled;
    }

    return [
        'stats' => $stats,
        'trackpoints' => $trackpoints,
        'bounds' => calculateBounds($trackpoints)
    ];
}

/**
 * Get EXIF data from photo
 *
 * @param string $photoPath Path to photo file
 * @return array|null EXIF data including timestamp and GPS coordinates
 */
function getPhotoExif($photoPath) {
    if (!file_exists($photoPath)) {
        return null;
    }

    $exif = @exif_read_data($photoPath, 0, true);

    if (!$exif) {
        return null;
    }

    $data = [];

    // Get timestamp
    if (isset($exif['EXIF']['DateTimeOriginal'])) {
        $data['timestamp'] = strtotime($exif['EXIF']['DateTimeOriginal']);
    } elseif (isset($exif['IFD0']['DateTime'])) {
        $data['timestamp'] = strtotime($exif['IFD0']['DateTime']);
    } else {
        $data['timestamp'] = null;
    }

    // Get GPS coordinates
    if (isset($exif['GPS']['GPSLatitude']) && isset($exif['GPS']['GPSLongitude'])) {
        $data['lat'] = getGPSCoordinate(
            $exif['GPS']['GPSLatitude'],
            $exif['GPS']['GPSLatitudeRef']
        );
        $data['lon'] = getGPSCoordinate(
            $exif['GPS']['GPSLongitude'],
            $exif['GPS']['GPSLongitudeRef']
        );
    } else {
        $data['lat'] = null;
        $data['lon'] = null;
    }

    return $data;
}

/**
 * Convert GPS EXIF data to decimal degrees
 */
function getGPSCoordinate($coordinate, $ref) {
    $degrees = count($coordinate) > 0 ? gpsToDecimal($coordinate[0]) : 0;
    $minutes = count($coordinate) > 1 ? gpsToDecimal($coordinate[1]) : 0;
    $seconds = count($coordinate) > 2 ? gpsToDecimal($coordinate[2]) : 0;

    $decimal = $degrees + ($minutes / 60) + ($seconds / 3600);

    if ($ref == 'S' || $ref == 'W') {
        $decimal *= -1;
    }

    return $decimal;
}

/**
 * Convert GPS fraction to decimal
 */
function gpsToDecimal($coordinate) {
    if (strpos($coordinate, '/') === false) {
        return (float) $coordinate;
    }

    $parts = explode('/', $coordinate);
    if (count($parts) <= 0) {
        return 0;
    }

    if (count($parts) == 1) {
        return (float) $parts[0];
    }

    return (float) $parts[0] / (float) $parts[1];
}

/**
 * Match photos to GPX track points based on timestamps
 *
 * @param array $photos Array of photos with EXIF data
 * @param array $trackpoints Array of GPX track points
 * @return array Photos with matched GPS coordinates
 */
function matchPhotosToTrack($photos, $trackpoints) {
    $matched = [];
    // Try to load external images metadata (images_metadata.json) from the page folder
    $externalMetaMap = [];
    if (!empty($photos)) {
        $firstFile = $photos[0]['file'] ?? null;
        if ($firstFile && method_exists($firstFile, 'page')) {
            $page = $firstFile->page();
            $pageRoot = $page ? $page->root() : null;

            // look for well-known filenames in the page folder
            if ($pageRoot) {
                $candidates = ['images_metadata.json', 'images-metadata.json', 'images.json', 'images_meta.json'];
                foreach ($candidates as $candidate) {
                    $path = $pageRoot . DIRECTORY_SEPARATOR . $candidate;
                    if (is_file($path)) {
                        $jc = @file_get_contents($path);
                        $arr = @json_decode($jc, true);
                        if (is_array($arr)) {
                            foreach ($arr as $item) {
                                if (!empty($item['filename'])) {
                                    $externalMetaMap[$item['filename']] = $item;
                                }
                            }
                            break;
                        }
                    }
                }
            }

            // Fallback: check attached JSON files on the page
            if (empty($externalMetaMap) && $page) {
                foreach ($page->files()->filterBy('extension', 'json') as $jf) {
                    $jc = @file_get_contents($jf->root());
                    $arr = @json_decode($jc, true);
                    if (is_array($arr)) {
                        foreach ($arr as $item) {
                            if (!empty($item['filename'])) {
                                $externalMetaMap[$item['filename']] = $item;
                            }
                        }
                        if (!empty($externalMetaMap)) break;
                    }
                }
            }
        }
    }

    foreach ($photos as $photo) {
        // If photo already has GPS coordinates from EXIF, use those
        if (!empty($photo['lat']) && !empty($photo['lon'])) {
            $matched[] = $photo;
            continue;
        }

        $file = $photo['file'] ?? null;

        // 1) Try file-sidecar fields (image meta text via Kirby) for Latitude/Longitude
        if ($file) {
            try {
                $fLat = $file->latitude();
                $fLon = $file->longitude();
                if (!empty($fLat) && !empty($fLon)) {
                    $photo['lat'] = floatval((string)$fLat);
                    $photo['lon'] = floatval((string)$fLon);
                    $matched[] = $photo;
                    continue;
                }
            } catch (Throwable $e) {
                // latitude() or longitude() may not be set on this file
            }
        }

        // 2) Try page-level external JSON metadata map (images_metadata.json)
        if ($file) {
            $fname = $file->filename();
            if (isset($externalMetaMap[$fname])) {
                $item = $externalMetaMap[$fname];
                $plat = $item['lat'] ?? ($item['latitude'] ?? null);
                $plng = $item['lng'] ?? ($item['lon'] ?? ($item['longitude'] ?? null));
                if ($plat !== null && $plng !== null) {
                    $photo['lat'] = floatval($plat);
                    $photo['lon'] = floatval($plng);
                    $matched[] = $photo;
                    continue;
                }
            }
        }

        // 3) Otherwise, try to match based on timestamp to trackpoints
        if (empty($photo['timestamp'])) {
            continue;
        }

        $closestPoint = null;
        $minDiff = PHP_INT_MAX;

        foreach ($trackpoints as $point) {
            if (empty($point['time'])) continue;
            $diff = abs($photo['timestamp'] - $point['time']);
            if ($diff < $minDiff) {
                $minDiff = $diff;
                $closestPoint = $point;
            }
        }

        if ($closestPoint && $minDiff < 300) {
            $photo['lat'] = $closestPoint['lat'];
            $photo['lon'] = $closestPoint['lon'];
            $matched[] = $photo;
        }
    }

    return $matched;
}

/**
 * Find the sort key for a photo located by GPS coordinates.
 * Returns the nearest trackpoint's timestamp, or its index if timestamps are absent.
 */
function findTrackSortKey($lat, $lon, $trackpoints) {
    $minDist  = PHP_INT_MAX;
    $bestPoint = null;
    $bestIdx  = -1;
    foreach ($trackpoints as $idx => $point) {
        $dist = haversineDistance($lat, $lon, $point['lat'], $point['lon']);
        if ($dist < $minDist) {
            $minDist  = $dist;
            $bestPoint = $point;
            $bestIdx  = $idx;
        }
    }
    if ($bestPoint === null) return null;
    return $bestPoint['time'] ?? $bestIdx;
}

/**
 * Calculate distance between two GPS coordinates using Haversine formula
 */
function haversineDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371; // km

    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);

    $a = sin($dLat / 2) * sin($dLat / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon / 2) * sin($dLon / 2);

    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

    return $earthRadius * $c;
}

/**
 * Format duration in seconds to human-readable format
 */
function formatDuration($seconds) {
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);

    if ($hours > 0) {
        return sprintf('%dh %dm', $hours, $minutes);
    } else {
        return sprintf('%dm', $minutes);
    }
}

/**
 * Calculate bounds (bounding box) for track points
 */
function calculateBounds($trackpoints) {
    if (empty($trackpoints)) {
        return null;
    }

    $lats = array_column($trackpoints, 'lat');
    $lons = array_column($trackpoints, 'lon');

    return [
        'min_lat' => min($lats),
        'max_lat' => max($lats),
        'min_lon' => min($lons),
        'max_lon' => max($lons)
    ];
}
