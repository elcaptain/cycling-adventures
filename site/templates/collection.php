<?php
snippet('header');

// Resolve referenced route pages and apply sort order.
// Draft pages are excluded — they break toPages() resolution and cause URL renumbering.
// Routes should be set to Unlisted (hidden from feed, but stable URLs and accessible here).
$routePages = $page->routes()->toPages()->filter(function($p) {
    return $p->status() !== 'draft';
});
$sortDir    = $page->sortorder()->toBool() ? 'desc' : 'asc';
$routePages = $routePages->sortBy('date', $sortDir);

// Build global track list with remapped indices.
// Each adventure's gpxDataAll() returns indices starting at 0 — they must be
// unique across the shared map so cross-highlight events target the right polyline.
$allTracks        = [];
$globalTrackIndex = 0;
$routeTrackMap    = []; // [routeId => ['offset' => N, 'count' => M]]

$trackColors = ['#2563eb', '#16a34a', '#d97706', '#7c3aed', '#e11d48'];

foreach ($routePages as $routePage) {
    $tracks = $routePage->gpxDataAll();
    $routeTrackMap[$routePage->id()] = [
        'offset' => $globalTrackIndex,
        'count'  => count($tracks),
    ];
    foreach ($tracks as $track) {
        $track['color']     = $trackColors[$globalTrackIndex % count($trackColors)];
        $track['index']     = $globalTrackIndex++;
        $track['routeName'] = $routePage->title()->value();
        $allTracks[]        = $track;
    }
}

$allTracksJson = esc(json_encode($allTracks), 'attr');

// Aggregate collection-level stats
$totalDistance   = 0;
$totalElevation  = 0;
$totalMovingTime = 0;
$totalDuration   = 0;
foreach ($routePages as $routePage) {
    $s = $routePage->gpxAggregatedStats();
    if ($s) {
        $totalDistance   += $s['distance']      ?? 0;
        $totalElevation  += $s['elevation_gain'] ?? 0;
        $totalMovingTime += $s['moving_time']    ?? 0;
        $totalDuration   += $s['duration']       ?? 0;
    }
}
$totalDistance  = round($totalDistance, 1);
$totalElevation = round($totalElevation, 0);

function formatCollectionDuration(int $seconds): string {
    $h = intdiv($seconds, 3600);
    $m = intdiv($seconds % 3600, 60);
    return $h > 0 ? "{$h}h {$m}m" : "{$m}m";
}
?>

<!-- Collection Header -->
<div class="max-w-7xl mx-auto mb-12 md:px-4">
    <div class="mb-4">
        <a href="<?= $site->url() ?>" class="text-accent-600 hover:underline">
            &larr; Back to all adventures
        </a>
    </div>

    <h1 class="text-4xl md:text-5xl font-bold mb-4">
        <?= $page->title() ?>
    </h1>

    <div class="flex items-center gap-4 text-accent-700">
        <time datetime="<?= $page->date()->toDate('Y-m-d') ?>">
            <?= $page->date()->toDate('F j, Y') ?>
        </time>
    </div>

    <?php if ($page->intro()->isNotEmpty()): ?>
        <p class="text-xl text-accent-700 mt-6">
            <?= $page->intro() ?>
        </p>
    <?php endif ?>

    <!-- Collection-level stats -->
    <?php if ($routePages->count() > 0): ?>
    <div class="ride-stats">
        <div class="ride-stat">
            <span class="ride-stat-label">Routes</span>
            <span class="ride-stat-value"><?= $routePages->count() ?></span>
        </div>
        <?php if ($totalDistance > 0): ?>
        <div class="ride-stat">
            <span class="ride-stat-label">Total Distance</span>
            <span class="ride-stat-value"><?= $totalDistance ?></span><span class="ride-stat-unit"> km</span>
        </div>
        <?php endif ?>
        <?php if ($totalElevation > 0): ?>
        <div class="ride-stat">
            <span class="ride-stat-label">Total Elevation</span>
            <span class="ride-stat-value"><?= $totalElevation ?></span><span class="ride-stat-unit"> m</span>
        </div>
        <?php endif ?>
        <?php if ($totalMovingTime > 0): ?>
        <div class="ride-stat">
            <span class="ride-stat-label">Moving Time</span>
            <span class="ride-stat-value"><?= formatCollectionDuration($totalMovingTime) ?></span>
        </div>
        <?php endif ?>
        <?php if ($totalDuration > 0): ?>
        <div class="ride-stat">
            <span class="ride-stat-label">Elapsed Time</span>
            <span class="ride-stat-value"><?= formatCollectionDuration($totalDuration) ?></span>
        </div>
        <?php endif ?>
    </div>
    <?php endif ?>
</div>

<!-- Two-column layout: route cards left, sticky map right -->
<div class="adventure-two-column">
    <!-- Left Column: Route Cards -->
    <div class="adventure-left-column">

        <?php foreach ($routePages as $routePage):
            $routeOffset     = $routeTrackMap[$routePage->id()]['offset'];
            $routeTrackCount = $routeTrackMap[$routePage->id()]['count'];
            $routeStats      = $routePage->gpxAggregatedStats();
            $sortedPhotos    = $routePage->galleryPhotosSorted();
            $coverFile       = $routePage->coverimage()->toFile();
            if ($coverFile) {
                $coverId    = $coverFile->id();
                $sortedPhotos = array_values(array_filter($sortedPhotos, fn($p) => $p->id() !== $coverId));
                array_unshift($sortedPhotos, $coverFile);
            }
            $routePhotos     = $sortedPhotos;
            $cardSlug        = $routePage->slug();

            // Build GPS lookup for photo strip hover markers
            $matchedPhotoMap = [];
            foreach ($routePage->matchedPhotos() as $mp) {
                if (!empty($mp['lat']) && !empty($mp['lon'])) {
                    $matchedPhotoMap[basename($mp['url'])] = [
                        'lat' => $mp['lat'],
                        'lon' => $mp['lon'],
                    ];
                }
            }

            // Build per-card elevation profile with globally remapped trackIndex values
            $cardElevationProfile = [];
            $cardTracks           = $routePage->gpxDataAll();
            $cumulativeDistance   = 0;
            $lastPoint            = null;
            $localTrackIdx        = 0;

            foreach ($cardTracks as $cardTrack) {
                $globalIdx = $routeOffset + $localTrackIdx;
                $pointIdx  = 0;
                foreach ($cardTrack['trackpoints'] as $point) {
                    if ($point['elevation'] === null) {
                        $lastPoint = $point;
                        $pointIdx++;
                        continue;
                    }
                    if ($lastPoint !== null) {
                        $dLat = deg2rad($point['lat'] - $lastPoint['lat']);
                        $dLon = deg2rad($point['lon'] - $lastPoint['lon']);
                        $a = sin($dLat / 2) * sin($dLat / 2) +
                             cos(deg2rad($lastPoint['lat'])) * cos(deg2rad($point['lat'])) *
                             sin($dLon / 2) * sin($dLon / 2);
                        $cumulativeDistance += 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));
                    }
                    $cardElevationProfile[] = [
                        'distance'   => round($cumulativeDistance, 2),
                        'elevation'  => round($point['elevation'], 1),
                        'trackIndex' => $globalIdx,
                        'pointIndex' => $pointIdx,
                    ];
                    $lastPoint = $point;
                    $pointIdx++;
                }
                $localTrackIdx++;
            }
            $cardElevationJson = esc(json_encode($cardElevationProfile), 'attr');
        ?>

        <article
            class="adventure-card collection-route-card p-6"
            id="route-card-<?= $cardSlug ?>"
            data-route-global-offset="<?= $routeOffset ?>"
            data-route-track-count="<?= $routeTrackCount ?>"
            style="--track-color: <?= $trackColors[$routeOffset % count($trackColors)] ?>"
        >
            <!-- Route title and date -->
            <h2 class="font-display text-2xl font-bold mb-1 text-accent-900">
                <?= $routePage->title() ?>
            </h2>

            <!-- Route stats -->
            <?php if ($routeStats): ?>
            <div class="daystats-stats">
                <?php if (isset($routeStats['distance'])): ?>
                <span class="ride-stat-value distance"><?= $routeStats['distance'] ?> km</span>
                <?php endif ?>
                <?php if (isset($routeStats['elevation_gain'])): ?>
                <span class="ride-stat-value elevation"><?= $routeStats['elevation_gain'] ?> m</span>
                <?php endif ?>
                <?php if (isset($routeStats['moving_time_formatted'])): ?>
                <span class="ride-stat-value moving-time"><?= $routeStats['moving_time_formatted'] ?></span>
                <?php endif ?>
                <?php if (isset($routeStats['avg_speed'])): ?>
                <span class="ride-stat-value speed"><?= $routeStats['avg_speed'] ?> km/h</span>
                <?php endif ?>
            </div>
            <?php endif ?>
            <time class="text-sm text-accent-600" datetime="<?= $routePage->date()->toDate('Y-m-d') ?>">
                <?= $routePage->date()->toDate('F j, Y') ?>
            </time>
            <!-- Description -->
            <?php if ($routePage->description()->isNotEmpty()): ?>
            <p class="text-accent-800 mb-4">
                <?= $routePage->description() ?>
            </p>
            <?php endif ?>

            <!-- Text and heading blocks only (images go in the photo strip) -->
            <?php if ($routePage->layout()->isNotEmpty()): ?>
            <div class="prose prose-sm max-w-none mb-4">
                <?php foreach ($routePage->layout()->toBlocks() as $block): ?>
                    <?php if ($block->type() === 'heading'): ?>
                        <<?= $block->level() ?> class="font-bold mt-3 mb-1">
                            <?= $block->text() ?>
                        </<?= $block->level() ?>>
                    <?php elseif ($block->type() === 'text'): ?>
                        <div><?= $block->text()->kirbytext() ?></div>
                    <?php endif ?>
                <?php endforeach ?>
            </div>
            <?php endif ?>

            <!-- Horizontal photo strip -->
            <?php if (!empty($routePhotos)): ?>
            <div class="flex gap-2 overflow-x-auto py-2 mt-4">
                <?php foreach ($routePhotos as $photo):
                    $photoFile = is_array($photo) ? null : $photo;
                    $photoUrl  = is_array($photo) ? ($photo['thumb'] ?? $photo['url'] ?? null) : null;
                    if ($photoFile && method_exists($photoFile, 'crop')):
                    $photoGps = $matchedPhotoMap[$photoFile->filename()] ?? null;
                ?>
                <img
                    src="<?= $photoFile->resize(300)->url() ?>"
                    alt="<?= $photoFile->alt() ?>"
                    class="h-32 w-auto flex-shrink-0 rounded-md object-cover"
                    loading="lazy"
                    <?php if ($photoGps): ?>data-photo-lat="<?= $photoGps['lat'] ?>" data-photo-lon="<?= $photoGps['lon'] ?>"<?php endif ?>
                >
                <?php elseif ($photoUrl): ?>
                <img
                    src="<?= $photoUrl ?>"
                    alt=""
                    class="h-32 w-auto flex-shrink-0 rounded-md object-cover"
                    loading="lazy"
                >
                <?php endif ?>
                <?php endforeach ?>
            </div>
            <?php endif ?>

            <!-- Elevation profile -->
            <?php if (!empty($cardElevationProfile)): ?>
            <div class="elevation-profile-sidebar mt-4">
                <canvas
                    class="collection-elevation-canvas"
                    data-elevation-profile="<?= $cardElevationJson ?>"
                    data-route-global-offset="<?= $routeOffset ?>"
                ></canvas>
            </div>
            <?php endif ?>
        </article>

        <?php endforeach ?>

    </div><!-- end left column -->

    <!-- Right Column: Sticky map (no elevation profile below) -->
    <div class="adventure-right-column">
        <div class="map-sticky-wrapper">
            <div
                id="adventure-map"
                class="map-container map-sticky"
                data-tracks="<?= $allTracksJson ?>"
                data-photos="<?= esc(json_encode([]), 'attr') ?>"
                data-hide-endpoints="true"
            ></div>
        </div>
    </div><!-- end right column -->

</div><!-- end two-column layout -->

<?php snippet('footer') ?>
