<?php
/**
 * Home Template
 *
 * Displays a paginated list of all cycling adventures
 */

snippet('header');

// Get all adventures, sorted by date (newest first)
$adventures = page('adventures');
$adventuresList = $adventures ? $adventures->children()->listed()->sortBy('date', 'desc') : null;

// Pagination: 9 adventures per page
$perPage = 9;
$paginatedAdventures = $adventuresList ? $adventuresList->paginate($perPage) : null;
$pagination = $paginatedAdventures ? $paginatedAdventures->pagination() : null;
?>

<!-- Hero Section -->
<div class="text-center mb-16">
    <?php if ($page->headline()->isNotEmpty()): ?>
        <h1 class="text-5xl font-bold mb-4">
            <?= $page->headline() ?>
        </h1>
    <?php else: ?>
        <h1 class="text-5xl font-bold mb-4">
            <?= $page->title() ?>
        </h1>
    <?php endif ?>

    <?php if ($page->intro()->isNotEmpty()): ?>
        <p class="text-xl text-gray-600 max-w-2xl mx-auto">
            <?= $page->intro() ?>
        </p>
    <?php endif ?>
</div>

<!-- Adventures Grid -->
<?php if ($paginatedAdventures && $paginatedAdventures->count() > 0): ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mb-12">
        <?php foreach ($paginatedAdventures as $adventure): ?>
            <?php if ($adventure->template()->name() === 'collection'): ?>
                <?php snippet('collection-card', ['collection' => $adventure]) ?>
            <?php else: ?>
                <?php snippet('adventure-card', ['adventure' => $adventure]) ?>
            <?php endif ?>
        <?php endforeach ?>
    </div>

    <!-- Pagination -->
    <?php if ($pagination->hasPages()): ?>
        <nav class="pagination" role="navigation">
            <?php if ($pagination->hasPrevPage()): ?>
                <a href="<?= $pagination->prevPageURL() ?>" class="pagination-link">
                    &larr; Previous
                </a>
            <?php endif ?>

            <?php foreach ($pagination->range(5) as $r): ?>
                <a
                    href="<?= $pagination->pageURL($r) ?>"
                    class="pagination-link <?= $pagination->page() == $r ? 'is-active' : '' ?>"
                >
                    <?= $r ?>
                </a>
            <?php endforeach ?>

            <?php if ($pagination->hasNextPage()): ?>
                <a href="<?= $pagination->nextPageURL() ?>" class="pagination-link">
                    Next &rarr;
                </a>
            <?php endif ?>
        </nav>
    <?php endif ?>

<?php else: ?>
    <!-- No adventures yet -->
    <div class="text-center py-24">
        <svg class="w-24 h-24 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path>
        </svg>
        <h2 class="text-2xl font-bold text-gray-600 mb-2">No adventures yet</h2>
        <p class="text-gray-500">Start by adding your first cycling adventure in the Kirby Panel.</p>
    </div>
<?php endif ?>

<?php snippet('footer') ?>
