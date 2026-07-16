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
        'permalinkUrl' => function () {
            return url('adventures/' . $this->num() . '-' . $this->slug());
        },

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
                $exif = cachedGetPhotoExif($photo->root());

                $timestamp = null;
                $lat = null;
                $lon = null;

                if ($exif) {
                    $timestamp = $exif['timestamp'] ?? null;
                    $lat = $exif['lat'] ?? null;
                    $lon = $exif['lon'] ?? null;
                }

                if (!$lat) {
                    try {
                        $latVal = $photo->latitude();
                        if (!empty((string)$latVal)) {
                            $lat = floatval((string)$latVal);
                        }
                    } catch (Throwable $e) {
                        // latitude() may not exist
                    }
                }

                if (!$lon) {
                    try {
                        $lonVal = $photo->longitude();
                        if (!empty((string)$lonVal)) {
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

            $matched = matchPhotosToTrack($photos, $allTrackpoints);

            $privacy = loadPrivacyZones($this->slug());
            return filterPhotosForPrivacy($matched, $privacy['zones'], $privacy['radius']);
        },

        /**
         * Get GPX data for all uploaded GPX files, one entry per file/day
         */
        'gpxDataAll' => function() {
            $page = $this;
            return gpxDataAllMemo($this->id(), function() use ($page) {
                $gpxFiles = $page->gpxfile()->toFiles();
                if ($gpxFiles->count() === 0) {
                    return [];
                }
                $privacy = loadPrivacyZones($page->slug());
                $colors  = ['#2563eb', '#16a34a', '#d97706', '#7c3aed', '#e11d48'];
                $tracks  = [];
                $index   = 0;
                foreach ($gpxFiles as $gpxFile) {
                    $parsed = cachedParseGPX($gpxFile->root());
                    if ($parsed) {
                        $pts = filterTrackpointsForPrivacy($parsed['trackpoints'], $privacy['zones'], $privacy['radius']);
                        $tracks[] = [
                            'index'       => $index,
                            'color'       => $colors[$index % count($colors)],
                            'trackpoints' => $pts,
                            'stats'       => $parsed['stats'],
                            'bounds'      => calculateBounds($pts) ?? $parsed['bounds'],
                        ];
                        $index++;
                    }
                }
                return $tracks;
            });
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

            $photosWithExif = $this->photosWithExif();
            $clockOffset = computeClockOffset($photosWithExif, $allTrackpoints);

            $sortKeys = [];
            foreach ($photosWithExif as $photoData) {
                $filename = $photoData['file']->filename();

                if ($photoData['timestamp'] !== null) {
                    // Phone or Camera: sort by adjusted EXIF capture time
                    $key = $photoData['timestamp'] + $clockOffset;
                } elseif (!empty($photoData['lat']) && !empty($photoData['lon'])) {
                    // Komoot import (sidecar GPS only): infer capture time from nearest track position
                    $key = findTrackSortKey($photoData['lat'], $photoData['lon'], $allTrackpoints);
                } else {
                    $key = null;
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

    $clockOffset = computeClockOffset($photos, $trackpoints);

    foreach ($photos as $photo) {
        // If photo already has GPS coordinates from EXIF, use those directly —
        // always show the photo at its own location, regardless of when it was taken.
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
                if (!empty((string)$fLat) && !empty((string)$fLon)) {
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

        // 3) Timestamp matching — adjusted for camera clock offset vs GPX UTC
        if (empty($photo['timestamp'])) {
            continue;
        }

        $closestPoint = null;
        $minDiff = PHP_INT_MAX;

        foreach ($trackpoints as $point) {
            if (empty($point['time'])) continue;
            $diff = abs(($photo['timestamp'] + $clockOffset) - $point['time']);
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
 * Find the sort key for a photo along the track.
 * When lat/lon are provided, finds the nearest trackpoint spatially.
 * When only a UTC timestamp is provided, finds the nearest trackpoint by time.
 * Returns the matched trackpoint's UTC timestamp (comparable across both modes).
 */
function findTrackSortKey($lat, $lon, $trackpoints, $utcTimestamp = null) {
    $bestPoint = null;

    if ($lat !== null && $lon !== null) {
        $minDist = PHP_INT_MAX;
        foreach ($trackpoints as $point) {
            $dist = haversineDistance($lat, $lon, $point['lat'], $point['lon']);
            if ($dist < $minDist) {
                $minDist   = $dist;
                $bestPoint = $point;
            }
        }
    } elseif ($utcTimestamp !== null) {
        $minDiff = PHP_INT_MAX;
        foreach ($trackpoints as $point) {
            if (empty($point['time'])) continue;
            $diff = abs($utcTimestamp - $point['time']);
            if ($diff < $minDiff) {
                $minDiff   = $diff;
                $bestPoint = $point;
            }
        }
    }

    if ($bestPoint === null) return null;
    return $bestPoint['time'] ?? null;
}

/**
 * Compute the clock offset (in seconds) between photo EXIF timestamps and GPX UTC times.
 * Uses photos that have both GPS and a timestamp as ground-truth references:
 * offset = (nearest trackpoint time by GPS distance) - (photo EXIF timestamp).
 * Returns the median offset; 0 if no reliable references are found.
 */
function computeClockOffset(array $photos, array $trackpoints): int {
    if (empty($trackpoints)) return 0;

    $offsets = [];
    foreach ($photos as $photo) {
        if (empty($photo['lat']) || empty($photo['lon']) || empty($photo['timestamp'])) continue;

        $minDist  = PHP_INT_MAX;
        $bestTime = null;
        foreach ($trackpoints as $tp) {
            if (empty($tp['time'])) continue;
            $dist = haversineDistance($photo['lat'], $photo['lon'], $tp['lat'], $tp['lon']);
            if ($dist < $minDist) {
                $minDist  = $dist;
                $bestTime = $tp['time'];
            }
        }
        // Only use photos whose GPS is within 200 m of the track
        if ($bestTime !== null && $minDist < 0.2) {
            $offsets[] = $bestTime - $photo['timestamp'];
        }
    }

    if (empty($offsets)) return 0;
    sort($offsets);
    return $offsets[(int)(count($offsets) / 2)];
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

/**
 * Per-request memoization for gpxDataAll().
 * Stores results in a static array keyed by page ID so that the three calls per
 * adventure page load (gpxAggregatedStats, direct, matchedPhotos) only parse once.
 */
function gpxDataAllMemo(string $pageId, callable $compute): array {
    static $cache = [];
    if (!array_key_exists($pageId, $cache)) {
        $cache[$pageId] = $compute();
    }
    return $cache[$pageId];
}

/**
 * Cross-request cached wrapper for parseGPX().
 * Cache key = md5(path) + mtime, so replacing a GPX file automatically invalidates.
 */
function cachedParseGPX(string $gpxPath): ?array {
    $mtime = @filemtime($gpxPath);
    if ($mtime === false) return parseGPX($gpxPath);

    $cacheKey = 'gpx_' . md5($gpxPath) . '_' . $mtime;
    $cache    = kirby()->cache('adventure-helpers');

    $cached = $cache->get($cacheKey);
    if ($cached !== null) return $cached;

    $result = parseGPX($gpxPath);
    if ($result !== null) {
        $cache->set($cacheKey, $result, 0);
    }
    return $result;
}

/**
 * Cross-request cached wrapper for getPhotoExif().
 * Uses ['__none__' => true] sentinel to cache null results (photo has no EXIF).
 */
function cachedGetPhotoExif(string $photoPath): ?array {
    $mtime = @filemtime($photoPath);
    if ($mtime === false) return getPhotoExif($photoPath);

    $cacheKey = 'exif_' . md5($photoPath) . '_' . $mtime;
    $cache    = kirby()->cache('adventure-helpers');

    $cached = $cache->get($cacheKey);
    if ($cached !== null) {
        return isset($cached['__none__']) ? null : $cached;
    }

    $result = getPhotoExif($photoPath);
    $cache->set($cacheKey, $result ?? ['__none__' => true], 0);
    return $result;
}

/**
 * Load privacy zones from site settings, applying a per-tour deterministic random offset
 * to each zone center. The offset prevents reverse-triangulation of the true address by
 * ensuring the cut point varies across tours (±150 m, derived from md5 of page slug).
 */
function loadPrivacyZones(string $pageSlug): array {
    $site     = kirby()->site();
    $rawZones = [];
    foreach ($site->privacyZones()->toStructure() as $z) {
        $lat = floatval((string)$z->lat());
        $lon = floatval((string)$z->lon());
        if ($lat && $lon) {
            $rawZones[] = ['lat' => $lat, 'lon' => $lon];
        }
    }
    $radius = floatval((string)$site->privacyRadius()) ?: 500;

    $offsetZones = [];
    foreach ($rawZones as $i => $zone) {
        $hash   = md5($pageSlug . '_zone_' . $i);
        $latM   = (hexdec(substr($hash, 0, 8)) / 0xFFFFFFFF - 0.5) * 2 * 150;
        $lonM   = (hexdec(substr($hash, 8, 8)) / 0xFFFFFFFF - 0.5) * 2 * 150;
        $latDeg = $latM / 111320;
        $lonDeg = $lonM / (111320 * cos(deg2rad($zone['lat'])));
        $offsetZones[] = [
            'lat' => $zone['lat'] + $latDeg,
            'lon' => $zone['lon'] + $lonDeg,
        ];
    }

    return ['zones' => $offsetZones, 'radius' => $radius];
}

/**
 * Check whether a point (with 'lat'/'lon' keys) falls within the radius of any privacy zone.
 * Radius is in meters; haversineDistance returns km.
 */
function isInAnyZone(array $point, array $zones, float $radius): bool {
    $radiusKm = $radius / 1000;
    foreach ($zones as $zone) {
        if (haversineDistance($point['lat'], $point['lon'], $zone['lat'], $zone['lon']) <= $radiusKm) {
            return true;
        }
    }
    return false;
}

/**
 * Strip leading and trailing trackpoints that fall inside any privacy zone.
 * Points in the middle of the route are never removed even if they pass through a zone.
 */
function filterTrackpointsForPrivacy(array $trackpoints, array $zones, float $radius): array {
    if (empty($zones) || empty($trackpoints)) return $trackpoints;
    $start = 0;
    $end   = count($trackpoints) - 1;
    while ($start <= $end && isInAnyZone($trackpoints[$start], $zones, $radius)) $start++;
    while ($end >= $start && isInAnyZone($trackpoints[$end],   $zones, $radius)) $end--;
    if ($start > $end) return [];
    return array_slice($trackpoints, $start, $end - $start + 1);
}

/**
 * Remove photos whose GPS coordinates fall inside any privacy zone.
 * Photos without coordinates are kept (they don't appear on the map anyway).
 */
function filterPhotosForPrivacy(array $photos, array $zones, float $radius): array {
    if (empty($zones)) return $photos;
    return array_values(array_filter($photos, function($photo) use ($zones, $radius) {
        if (empty($photo['lat']) || empty($photo['lon'])) return true;
        return !isInAnyZone(['lat' => $photo['lat'], 'lon' => $photo['lon']], $zones, $radius);
    }));
}
