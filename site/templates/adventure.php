<?php
/**
 * Adventure Template
 *
 * Displays a single cycling adventure with:
 * - Ride statistics
 * - Interactive map with GPX track and photo markers
 * - Flexible content layout
 */

snippet('header');

// Get GPX data
$gpxData = $page->gpxData();
$gpxStats = $gpxData ? $gpxData['stats'] : null;
$trackpoints = $gpxData ? $gpxData['trackpoints'] : [];

// Get photos with GPS coordinates
$matchedPhotos = $page->matchedPhotos();
?>

<!-- Adventure Header -->
<div class="max-w-4xl mx-auto mb-12">
    <div class="mb-4">
        <a href="<?= $site->url() ?>" class="text-ride-primary hover:underline">
            &larr; Back to all adventures
        </a>
    </div>

    <h1 class="text-5xl font-bold mb-4">
        <?= $page->title() ?>
    </h1>

    <div class="flex items-center gap-4 text-gray-600">
        <time datetime="<?= $page->date()->toDate('Y-m-d') ?>">
            <?= $page->date()->toDate('F j, Y') ?>
        </time>
    </div>

    <?php if ($page->description()->isNotEmpty()): ?>
        <p class="text-xl text-gray-600 mt-6">
            <?= $page->description() ?>
        </p>
    <?php endif ?>
</div>

<!-- Ride Statistics -->
<?php if ($gpxStats || $page->distance()->isNotEmpty()): ?>
<div class="max-w-5xl mx-auto mb-12">
    <div class="ride-stats">
        <!-- Distance -->
        <?php if ($gpxStats && isset($gpxStats['distance'])): ?>
            <div class="ride-stat">
                <span class="ride-stat-value"><?= $gpxStats['distance'] ?></span>
                <span class="ride-stat-label">Distance (km)</span>
            </div>
        <?php elseif ($page->distance()->isNotEmpty()): ?>
            <div class="ride-stat">
                <span class="ride-stat-value"><?= $page->distance() ?></span>
                <span class="ride-stat-label">Distance (km)</span>
            </div>
        <?php endif ?>

        <!-- Elevation Gain -->
        <?php if ($gpxStats && isset($gpxStats['elevation_gain'])): ?>
            <div class="ride-stat">
                <span class="ride-stat-value"><?= $gpxStats['elevation_gain'] ?></span>
                <span class="ride-stat-label">Elevation (m)</span>
            </div>
        <?php elseif ($page->elevation()->isNotEmpty()): ?>
            <div class="ride-stat">
                <span class="ride-stat-value"><?= $page->elevation() ?></span>
                <span class="ride-stat-label">Elevation (m)</span>
            </div>
        <?php endif ?>

        <!-- Duration -->
        <?php if ($gpxStats && isset($gpxStats['duration_formatted'])): ?>
            <div class="ride-stat">
                <span class="ride-stat-value"><?= $gpxStats['duration_formatted'] ?></span>
                <span class="ride-stat-label">Duration</span>
            </div>
        <?php elseif ($page->duration()->isNotEmpty()): ?>
            <div class="ride-stat">
                <span class="ride-stat-value"><?= $page->duration() ?></span>
                <span class="ride-stat-label">Duration</span>
            </div>
        <?php endif ?>

        <!-- Moving Time -->
        <?php if ($gpxStats && isset($gpxStats['moving_time_formatted'])): ?>
            <div class="ride-stat">
                <span class="ride-stat-value"><?= $gpxStats['moving_time_formatted'] ?></span>
                <span class="ride-stat-label">Moving Time</span>
            </div>
        <?php elseif ($page->movingtime()->isNotEmpty()): ?>
            <div class="ride-stat">
                <span class="ride-stat-value"><?= $page->movingtime() ?></span>
                <span class="ride-stat-label">Moving Time</span>
            </div>
        <?php endif ?>

        <!-- Average Speed -->
        <?php if ($gpxStats && isset($gpxStats['avg_speed'])): ?>
            <div class="ride-stat">
                <span class="ride-stat-value"><?= $gpxStats['avg_speed'] ?></span>
                <span class="ride-stat-label">Avg Speed (km/h)</span>
            </div>
        <?php elseif ($page->avgspeed()->isNotEmpty()): ?>
            <div class="ride-stat">
                <span class="ride-stat-value"><?= $page->avgspeed() ?></span>
                <span class="ride-stat-label">Avg Speed (km/h)</span>
            </div>
        <?php endif ?>
    </div>
</div>
<?php endif ?>

<!-- Interactive Map -->
<?php if (!empty($trackpoints)): ?>
<div class="max-w-6xl mx-auto">
    <div
        id="adventure-map"
        class="map-container"
        data-trackpoints="<?= esc(json_encode($trackpoints), 'attr') ?>"
        data-photos="<?= esc(json_encode($matchedPhotos), 'attr') ?>"
    ></div>
</div>
<?php endif ?>

<!-- Content Layout (Flexible Blocks) -->
<?php if ($page->layout()->isNotEmpty()): ?>
<div class="max-w-4xl mx-auto">
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
                            class="w-full rounded-lg shadow-lg"
                        >
                        <?php if ($block->caption()->isNotEmpty()): ?>
                            <figcaption class="text-sm text-gray-600 mt-2 text-center">
                                <?= $block->caption() ?>
                            </figcaption>
                        <?php endif ?>
                    </figure>
                <?php endif ?>

            <?php elseif ($block->type() == 'gallery'): ?>
                <!-- Gallery Block -->
                <?php if ($block->images()->isNotEmpty()): ?>
                    <div class="photo-gallery">
                        <?php foreach ($block->images()->toFiles() as $image): ?>
                            <div class="photo-gallery-item">
                                <img
                                    src="<?= $image->crop(600, 400)->url() ?>"
                                    srcset="<?= $image->srcset([400, 600, 800]) ?>"
                                    alt="<?= $image->alt() ?>"
                                    class="photo-gallery-image"
                                >
                            </div>
                        <?php endforeach ?>
                    </div>
                <?php endif ?>

            <?php endif ?>
        </div>
    <?php endforeach ?>
</div>
<?php endif ?>

<!-- Photo Gallery (if no layout blocks, show all images) -->
<?php if ($page->layout()->isEmpty() && $page->images()->count() > 0): ?>
<div class="max-w-6xl mx-auto mt-12">
    <h2 class="text-3xl font-bold mb-8">Photos</h2>
    <div class="photo-gallery">
        <?php foreach ($page->images() as $image): ?>
            <div class="photo-gallery-item">
                <img
                    src="<?= $image->crop(600, 400)->url() ?>"
                    srcset="<?= $image->srcset([400, 600, 800]) ?>"
                    alt="<?= $image->alt() ?>"
                    class="photo-gallery-image"
                >
            </div>
        <?php endforeach ?>
    </div>
</div>
<?php endif ?>
<?php snippet('footer') ?>
