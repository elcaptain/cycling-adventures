<?php
/**
 * Adventure Card Snippet
 *
 * Displays a single adventure in the list
 * Used on the homepage/adventures list page
 *
 * Variables:
 * @var Page $adventure The adventure page object
 */
?>

<article class="adventure-card">
    <!-- Cover Image -->
    <?php if ($coverImage = $adventure->images()->first()): ?>
        <a href="<?= $adventure->url() ?>">
            <img
                src="<?= $coverImage->crop(800, 450)->url() ?>"
                srcset="<?= $coverImage->srcset([400, 800, 1200]) ?>"
                alt="<?= $adventure->title() ?>"
                class="adventure-card-image"
            >
        </a>
    <?php endif ?>

    <!-- Content -->
    <div class="adventure-card-content">
        <!-- Title -->
        <h2 class="adventure-card-title">
            <a href="<?= $adventure->url() ?>">
                <?= $adventure->title() ?>
            </a>
        </h2>

        <!-- Meta info -->
        <div class="adventure-card-meta">
            <!-- Date -->
            <span class="flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                <?= $adventure->date()->toDate('M j, Y') ?>
            </span>

            <!-- Distance -->
            <?php if ($adventure->distance()->isNotEmpty()): ?>
            <span class="flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                </svg>
                <?= $adventure->distance() ?> km
            </span>
            <?php endif ?>

            <!-- Elevation -->
            <?php if ($adventure->elevation()->isNotEmpty()): ?>
            <span class="flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path>
                </svg>
                <?= $adventure->elevation() ?> m
            </span>
            <?php endif ?>
        </div>

        <!-- Description -->
        <?php if ($adventure->description()->isNotEmpty()): ?>
        <p class="adventure-card-description">
            <?= $adventure->description() ?>
        </p>
        <?php endif ?>
    </div>
</article>
