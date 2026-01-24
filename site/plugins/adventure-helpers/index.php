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

                if ($exif) {
                    $photoData[] = [
                        'file' => $photo,
                        'url' => $photo->url(),
                        'thumb' => $photo->resize(400)->url(),
                        'timestamp' => $exif['timestamp'],
                        'lat' => $exif['lat'],
                        'lon' => $exif['lon']
                    ];
                }
            }

            return $photoData;
        },

        /**
         * Get matched photos (photos matched to GPX track points)
         */
        'matchedPhotos' => function() {
            $gpxData = $this->gpxData();
            $photos = $this->photosWithExif();

            if (!$gpxData || empty($photos)) {
                return [];
            }

            return matchPhotosToTrack($photos, $gpxData['trackpoints']);
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

        // Calculate moving time (simplified - actual calculation would need speed analysis)
        // For now, assume 90% of total time is moving time
        $stats['moving_time'] = round($duration * 0.9);
        $stats['moving_time_formatted'] = formatDuration($stats['moving_time']);

        // Average speed
        if ($duration > 0) {
            $stats['avg_speed'] = round(($totalDistance / ($duration / 3600)), 1);
        }
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

    foreach ($photos as $photo) {
        // If photo already has GPS coordinates from EXIF, use those
        if ($photo['lat'] && $photo['lon']) {
            $matched[] = $photo;
            continue;
        }

        // Otherwise, try to match based on timestamp
        if (!$photo['timestamp']) {
            continue;
        }

        // Find closest track point by timestamp
        $closestPoint = null;
        $minDiff = PHP_INT_MAX;

        foreach ($trackpoints as $point) {
            if (!$point['time']) {
                continue;
            }

            $diff = abs($photo['timestamp'] - $point['time']);

            if ($diff < $minDiff) {
                $minDiff = $diff;
                $closestPoint = $point;
            }
        }

        // Only match if within 5 minutes
        if ($closestPoint && $minDiff < 300) {
            $photo['lat'] = $closestPoint['lat'];
            $photo['lon'] = $closestPoint['lon'];
            $matched[] = $photo;
        }
    }

    return $matched;
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
