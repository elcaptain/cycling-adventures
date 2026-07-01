<?php
/**
 * Collection Card Snippet
 *
 * Displays a collection post in the home page adventure grid.
 * Shows aggregated stats from all referenced routes.
 *
 * @var Page $collection The collection page object
 */

// Aggregate distance + elevation across all referenced routes
$collectionDistance  = 0;
$collectionElevation = 0;
$routeCount          = 0;

$routePages = $collection->routes()->toPages();
foreach ($routePages as $routePage) {
    $s = $routePage->gpxAggregatedStats();
    if ($s) {
        $collectionDistance  += $s['distance']      ?? 0;
        $collectionElevation += $s['elevation_gain'] ?? 0;
    }
    $routeCount++;
}
$collectionDistance  = round($collectionDistance, 1);
$collectionElevation = round($collectionElevation, 0);

// Cover image: use collection's own cover, fall back to first route's cover
$coverImage = $collection->coverimage()->toFiles()->first();
if (!$coverImage) {
    foreach ($routePages as $routePage) {
        $coverImage = $routePage->coverimage()->toFiles()->first() ?? $routePage->images()->first();
        if ($coverImage) break;
    }
}
?>

<article class="adventure-card">
    <!-- Cover Image -->
    <?php if ($coverImage): ?>
        <a href="<?= $collection->permalinkUrl() ?>">
            <?php $focus = $coverImage->focus()->value(); ?>
            <img
                src="<?= $coverImage->crop(800, 450)->url() ?>"
                srcset="<?= $coverImage->srcset([400, 800, 1200]) ?>"
                alt="<?= $collection->title() ?>"
                class="adventure-card-image"
                loading="lazy"
                <?= $focus ? 'style="object-position: ' . $focus . '"' : '' ?>
            >
        </a>
    <?php endif ?>

    <!-- Content -->
    <div class="adventure-card-content">
        <!-- Title -->
        <h2 class="adventure-card-title">
            <a href="<?= $collection->permalinkUrl() ?>">
                <?= $collection->title() ?>
            </a>
        </h2>

        <!-- Meta -->
        <div class="adventure-card-meta">
            <span class="flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                <?= $collection->date()->toDate('M j, Y') ?>
            </span>

            <?php if ($routeCount > 0): ?>
            <span class="flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path>
                </svg>
                <?= $routeCount ?> route<?= $routeCount !== 1 ? 's' : '' ?>
            </span>
            <?php endif ?>

            <?php if ($collectionDistance > 0): ?>
            <span class="flex items-center gap-1">
                <img src="/assets/icons/icon-distance.svg" class="w-4 h-4" alt="">
                <?= $collectionDistance ?> km
            </span>
            <?php endif ?>

            <?php if ($collectionElevation > 0): ?>
            <span class="flex items-center gap-1">
                <img src="/assets/icons/icon-elevation.svg" class="w-4 h-4" alt="">
                <?= $collectionElevation ?> m
            </span>
            <?php endif ?>
        </div>

        <!-- Intro -->
        <?php if ($collection->intro()->isNotEmpty()): ?>
        <p class="adventure-card-description">
            <?= Str::excerpt($collection->intro(), 200) ?>
        </p>
        <?php endif ?>
    </div>
</article>
