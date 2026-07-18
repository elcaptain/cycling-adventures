<?php
/**
 * Adventure Template
 *
 * Displays a single cycling adventure with:
 * - Ride statistics
 * - Interactive map with GPX track and photo markers
 * - Flexible content layout
 * - Two layout options: single-column or two-column with sticky map
 */

snippet('header');

// Get GPX data
$gpxStats  = $page->gpxAggregatedStats();
$tracks    = $page->gpxDataAll();
$hasTracks = !empty($tracks);

// Get photos with GPS coordinates
$matchedPhotos = $page->matchedPhotos();

// Process elevation profile data (all tracks concatenated, cumulative distance, with trackIndex)
$elevationProfile = [];
$cumulativeDistance = 0;
$lastPoint = null;

foreach ($tracks as $track) {
    $pointIdx = 0;
    foreach ($track['trackpoints'] as $point) {
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
        $elevationProfile[] = [
            'distance'   => round($cumulativeDistance, 2),
            'elevation'  => round($point['elevation'], 1),
            'trackIndex' => $track['index'],
            'pointIndex' => $pointIdx,
        ];
        $lastPoint = $point;
        $pointIdx++;
    }
}

$tracksJson    = esc(json_encode($tracks), 'attr');
$photosJson    = esc(json_encode($matchedPhotos), 'attr');
$elevationJson = esc(json_encode($elevationProfile), 'attr');
?>

<!-- Adventure Header -->
<?php if ($page->layoutStyle()->toBool() === true): ?>
<div class="max-w-7xl mx-auto mb-12 md:px-4">
<?php else: ?>
<div class="max-w-4xl mx-auto mb-12">
<?php endif ?>
    <?php $parentCollection = $page->parentCollection() ?>
    <div class="mb-4">
        <?php if ($parentCollection): ?>
        <a href="<?= $parentCollection->permalinkUrl() ?>" class="text-accent-600 hover:underline">
            &larr; Back to <?= $parentCollection->title() ?>
        </a>
        <?php else: ?>
        <a href="<?= $site->url() ?>" class="text-accent-600 hover:underline">
            &larr; Back to all adventures
        </a>
        <?php endif ?>
    </div>

    <h1 class="text-4xl md:text-5xl font-bold mb-4">
        <?= $page->title() ?>
    </h1>

    <div class="flex items-center gap-4 text-accent-700">
        <time datetime="<?= $page->date()->toDate('Y-m-d') ?>">
            <?= $page->date()->toDate('F j, Y') ?>
        </time>
    </div>

    <?php if ($page->description()->isNotEmpty()): ?>
        <p class="text-xl mt-6">
            <?= $page->description() ?>
        </p>
    <?php endif ?>

    <!-- Ride Statistics -->
    <?php if ($gpxStats || $page->distance()->isNotEmpty()): ?>
        <div class="ride-stats">
            <!-- Days (multi-day trips only) -->
            <?php if ($gpxStats && ($gpxStats['days'] ?? 1) > 1): ?>
                <div class="ride-stat">
                    <span class="ride-stat-label">Routes</span>
                    <span class="ride-stat-value"><?= $gpxStats['days'] ?></span>
                </div>
            <?php endif ?>

            <!-- Distance -->
            <?php if ($gpxStats && isset($gpxStats['distance'])): ?>
                <div class="ride-stat">
                    <span class="ride-stat-label">Distance</span>
                    <span class="ride-stat-value"><?= $gpxStats['distance'] ?></span><span class="ride-stat-unit"> km</span>
                </div>
            <?php elseif ($page->distance()->isNotEmpty()): ?>
                <div class="ride-stat">
                    <span class="ride-stat-label">Distance</span>
                    <span class="ride-stat-value"><?= $page->distance() ?></span><span class="ride-stat-unit"> km</span>
                </div>
            <?php endif ?>

            <!-- Elevation Gain -->
            <?php if ($gpxStats && isset($gpxStats['elevation_gain'])): ?>
                <div class="ride-stat">
                    <span class="ride-stat-label">Elevation</span>
                    <span class="ride-stat-value"><?= $gpxStats['elevation_gain'] ?></span><span class="ride-stat-unit"> m</span>
                </div>
            <?php elseif ($page->elevation()->isNotEmpty()): ?>
                <div class="ride-stat">
                    <span class="ride-stat-label">Elevation</span>
                    <span class="ride-stat-value"><?= $page->elevation() ?></span><span class="ride-stat-unit"> m</span>
                </div>
            <?php endif ?>

            <!-- Duration -->
            <?php if ($gpxStats && isset($gpxStats['duration_formatted'])): ?>
                <div class="ride-stat">
                    <span class="ride-stat-label">Duration</span>
                    <span class="ride-stat-value"><?= $gpxStats['duration_formatted'] ?></span>
                </div>
            <?php elseif ($page->duration()->isNotEmpty()): ?>
                <div class="ride-stat">
                    <span class="ride-stat-label">Duration</span>
                    <span class="ride-stat-value"><?= $page->duration() ?></span>
                </div>
            <?php endif ?>

            <!-- Moving Time -->
            <?php if ($gpxStats && isset($gpxStats['moving_time_formatted'])): ?>
                <div class="ride-stat">
                    <span class="ride-stat-label">Moving Time</span>
                    <span class="ride-stat-value"><?= $gpxStats['moving_time_formatted'] ?></span>
                </div>
            <?php elseif ($page->movingtime()->isNotEmpty()): ?>
                <div class="ride-stat">
                    <span class="ride-stat-label">Moving Time</span>
                    <span class="ride-stat-value"><?= $page->movingtime() ?></span>
                </div>
            <?php endif ?>

            <!-- Average Speed -->
            <?php if ($gpxStats && isset($gpxStats['avg_speed'])): ?>
                <div class="ride-stat">
                    <span class="ride-stat-label">Avg Speed</span>
                    <span class="ride-stat-value"><?= $gpxStats['avg_speed'] ?></span><span class="ride-stat-unit"> km/h</span>
                </div>
            <?php elseif ($page->avgspeed()->isNotEmpty()): ?>
                <div class="ride-stat">
                    <span class="ride-stat-label">Avg Speed</span>
                    <span class="ride-stat-value"><?= $page->avgspeed() ?></span><span class="ride-stat-unit"> km/h</span>
                </div>
            <?php endif ?>
        </div>
    <?php endif ?>

</div>

<!-- Two-Column Layout Start -->
<?php if ($page->layoutStyle()->toBool() === true): ?>
<div class="adventure-two-column">
    <!-- Left Column: Content -->
    <div class="adventure-left-column">
<?php else: ?>
    <!-- Single Column: Map First -->
    <?php if ($hasTracks): ?>
    <div class="max-w-6xl mx-auto mb-12">
        <div
            id="adventure-map"
            class="map-container"
            data-tracks="<?= $tracksJson ?>"
            data-photos="<?= $photosJson ?>"
        ></div>
    </div>
    <?php endif ?>
<?php endif ?>

<!-- Content Layout (Flexible Blocks) -->
<?php if ($page->layout()->isNotEmpty()): ?>
<?php if ($page->layoutStyle()->toBool() === true): ?>
<div>
<?php else: ?>
<div class="max-w-4xl mx-auto">
<?php endif ?>
    <?php foreach ($page->layout()->toBlocks() as $block): ?>
        <div class="content-block">
            <?php if ($block->type() == 'heading'): ?>
                <!-- Heading Block -->
                <<?= $block->level() ?> class="text-3xl font-bold mb-4">
                    <?= $block->text() ?>
                </<?= $block->level() ?>>

            <?php elseif ($block->type() == 'text'): ?>
                <!-- Text Block -->
                <div class="content-block-text prose prose-lg">
                    <?= $block->text()->kirbytext() ?>
                </div>

            <?php elseif ($block->type() == 'image'): ?>
                <!-- Image Block -->
                <?php if ($image = $block->image()->toFile()): ?>
                    <figure class="mb-8">
                        <img
                            src="<?= $image->resize(1200)->url() ?>"
                            srcset="<?= $image->srcset([600, 900, 1200]) ?>"
                            alt="<?= $block->alt() ?>"
                            class="w-full rounded-sm shadow-lg"
                            data-photo-filename="<?= $image->filename() ?>"
                        >
                        <?php if ($block->caption()->isNotEmpty()): ?>
                            <figcaption class="text-sm text-accent-700 mt-2 text-center">
                                <?= $block->caption() ?>
                            </figcaption>
                        <?php endif ?>
                    </figure>
                <?php endif ?>

            <?php elseif ($block->type() == 'gallery'): ?>
                <!-- Gallery Block -->
                <?php $blockImages = $block->images()->toFiles(); ?>
                <?php if ($blockImages->isNotEmpty()): ?>
                    <div class="content-gallery content-gallery--cols-<?= min($blockImages->count(), 4) ?>">
                        <?php foreach ($blockImages as $image): ?>
                            <div class="photo-gallery-item">
                                <img
                                    src="<?= $image->crop(600, 400)->url() ?>"
                                    srcset="<?= $image->srcset([400, 600, 800]) ?>"
                                    alt="<?= $image->alt() ?>"
                                    class="photo-gallery-image"
                                    data-photo-filename="<?= $image->filename() ?>"
                                >
                            </div>
                        <?php endforeach ?>
                    </div>
                <?php endif ?>

            <?php elseif ($block->type() == 'daystats'): ?>
                <!-- Day Stats Block -->
                <?php
                    $blockGpxFile = $block->gpxfile()->toFile();
                    $blockGpxData = $blockGpxFile ? parseGPX($blockGpxFile->root()) : null;
                    $blockStats   = $blockGpxData ? $blockGpxData['stats'] : null;
                    $bLevel = in_array($block->level()->value(), ['h2', 'h3', 'h4'])
                                ? $block->level()->value() : 'h2';
                ?>
                <div class="daystats-block">
                    <?php if ($block->text()->isNotEmpty()): ?>
                    <<?= $bLevel ?> class="daystats-heading">
                        <?= $block->text() ?>
                    </<?= $bLevel ?>>
                    <?php endif ?>

                    <?php if ($blockStats): ?>
                    <div class="daystats-stats">
                        <?php if (isset($blockStats['distance'])): ?>
                        <div class="ride-stat">
                            <span class="ride-stat-value distance"><?= $blockStats['distance'] ?> km</span>
                        </div>
                        <?php endif ?>
                        <?php if (isset($blockStats['elevation_gain'])): ?>
                        <div class="ride-stat">
                            <span class="ride-stat-value elevation"><?= $blockStats['elevation_gain'] ?> m</span>
                        </div>
                        <?php endif ?>
                        <?php if (isset($blockStats['moving_time_formatted'])): ?>
                        <div class="ride-stat">
                            <span class="ride-stat-value moving-time"><?= $blockStats['moving_time_formatted'] ?></span>
                        </div>
                        <?php endif ?>
                        <?php if (isset($blockStats['avg_speed'])): ?>
                        <div class="ride-stat">
                            <span class="ride-stat-value speed"><?= $blockStats['avg_speed'] ?> km/h</span>
                        </div>
                        <?php endif ?>
                    </div>
                    <?php endif ?>
                </div>

            <?php endif ?>
        </div>
    <?php endforeach ?>
</div>
<?php endif ?>

<!-- Elevation Profile (single-column layout only; two-column renders it in the right column) -->
<?php if (!empty($elevationProfile) && !$page->layoutStyle()->toBool()): ?>
<div class="max-w-4xl mx-auto mb-12">
    <h2 class="text-3xl font-bold mb-6">Elevation Profile</h2>
    <div class="elevation-profile-container">
        <canvas
            id="elevation-chart"
            data-elevation-profile="<?= $elevationJson ?>"
        ></canvas>
    </div>
</div>
<?php endif ?>

<!-- Photo Gallery: curated photos from the photos field, sorted by capture time -->
<?php $galleryPhotos = $page->galleryPhotosSorted(); ?>
<?php if (!empty($galleryPhotos)): ?>
<div class="mt-12">
    <h2 class="text-3xl font-bold mb-8">Photos</h2>
    <div class="photo-gallery">
        <?php foreach ($galleryPhotos as $image): ?>
            <div class="photo-gallery-item">
                <img
                    src="<?= $image->crop(600, 600)->url() ?>"
                    srcset="<?= $image->srcset([400, 800]) ?>"
                    alt="<?= $image->alt() ?>"
                    class="photo-gallery-image"
                    data-photo-filename="<?= $image->filename() ?>"
                >
            </div>
        <?php endforeach ?>
    </div>
</div>
<?php endif ?>


<?php if ($page->layoutStyle()->toBool() === true): ?>
    </div><!-- end left column -->
    <!-- Right Column: Sticky Map + Elevation Profile -->
    <div class="adventure-right-column">
        <div class="map-sticky-wrapper">
            <div
                id="adventure-map"
                class="map-container map-sticky"
                data-tracks="<?= $tracksJson ?>"
                data-photos="<?= $photosJson ?>"
            ></div>
            <?php if (!empty($elevationProfile)): ?>
            <div class="elevation-profile-sidebar">
                <canvas
                    id="elevation-chart"
                    data-elevation-profile="<?= $elevationJson ?>"
                ></canvas>
            </div>
            <?php endif ?>
        </div>
    </div><!-- end right column -->
</div><!-- end two-column layout -->
<?php endif ?>

<?php snippet('footer') ?>
