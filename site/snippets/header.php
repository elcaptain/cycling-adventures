<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page->title() ?> | <?= $site->title() ?></title>

    <?php
    // Share metadata: same description and cover fallbacks as the cards
    $isPost        = in_array($page->template()->name(), ['adventure', 'collection']);
    $metaTitle     = $page->isHomePage() ? $site->title()->value() : $page->title()->value();
    $metaDesc      = null;
    $metaImage     = null;

    if ($page->description()->isNotEmpty()) {
        $metaDesc = $page->description()->kirbytext()->value();
    } elseif ($page->intro()->isNotEmpty()) {
        $metaDesc = $page->intro()->kirbytext()->value();
    } elseif ($page->layout()->isNotEmpty()) {
        foreach ($page->layout()->toBlocks() as $block) {
            if ($block->type() === 'text' && $block->text()->isNotEmpty()) {
                $metaDesc = $block->text()->kirbytext()->value();
                break;
            }
        }
    }
    $metaDesc = $metaDesc ? Str::excerpt($metaDesc, 200) : null;

    // Posts without any text: describe the ride by its stats instead
    if (!$metaDesc && $isPost) {
        $facts = [];
        if ($page->date()->isNotEmpty())      $facts[] = $page->date()->toDate('j F Y');
        if ($page->distance()->isNotEmpty())  $facts[] = number_format((float)$page->distance()->value(), 1) . ' km';
        if ($page->elevation()->isNotEmpty()) $facts[] = number_format((float)$page->elevation()->value(), 0, '.', ',') . ' m elevation';
        if ($page->movingtime()->isNotEmpty()) $facts[] = $page->movingtime()->value() . ' moving';
        $metaDesc = $facts ? 'Bike ride · ' . implode(' · ', $facts) : null;
    }

    if ($isPost) {
        $metaImage = $page->coverimage()->toFiles()->first() ?? $page->images()->first();
        if (!$metaImage && $page->template()->name() === 'collection') {
            foreach ($page->routes()->toPages() as $routePage) {
                $metaImage = $routePage->coverimage()->toFiles()->first() ?? $routePage->images()->first();
                if ($metaImage) break;
            }
        }
    }
    ?>

    <!-- Meta description -->
    <?php if ($metaDesc): ?>
    <meta name="description" content="<?= esc($metaDesc) ?>">
    <?php endif ?>

    <!-- Open Graph / social sharing -->
    <meta property="og:site_name" content="<?= esc($site->title()->value()) ?>">
    <meta property="og:title" content="<?= esc($metaTitle) ?>">
    <meta property="og:type" content="<?= $isPost ? 'article' : 'website' ?>">
    <meta property="og:url" content="<?= esc($page->url()) ?>">
    <?php if ($metaDesc): ?>
    <meta property="og:description" content="<?= esc($metaDesc) ?>">
    <?php endif ?>
    <?php if ($isPost && $page->date()->isNotEmpty()): ?>
    <meta property="article:published_time" content="<?= $page->date()->toDate('Y-m-d') ?>">
    <?php endif ?>
    <?php if ($metaImage): ?>
    <?php // quality 70 keeps it under WhatsApp's ~300 KB preview limit ?>
    <?php $ogImage = $metaImage->crop(1200, 630, ['quality' => 70]) ?>
    <meta property="og:image" content="<?= esc($ogImage->url()) ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="<?= esc($metaImage->alt()->or($metaTitle)->value()) ?>">
    <meta name="twitter:card" content="summary_large_image">
    <?php else: ?>
    <meta name="twitter:card" content="summary">
    <?php endif ?>

    <?php if (in_array($page->template()->name(), ['adventure', 'collection'])): ?>
    <!-- Leaflet CSS (for maps) -->
    <link rel="stylesheet" href="<?= url('assets/js/vendor/leaflet/leaflet.css') ?>">
    <?php endif ?>

    <!-- Fonts: Inter (body) + DynaPuff (display/headings) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DynaPuff:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Styles -->
    <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">

    <!-- Favicon (optional - add your own) -->
    <!-- <link rel="icon" type="image/svg+xml" href="/favicon.svg"> -->
</head>
<body class="bg-accent-50 text-accent-900 font-sans">

    <!-- Header -->
    <header class="bg-white border-b border-accent-100">
        <div class="container mx-auto px-4 py-6">
            <nav class="flex items-center justify-between">
                <a href="<?= $site->url() ?>" class="font-display text-2xl font-bold text-accent-500 hover:text-accent-700 transition-colors no-underline">
                    <?= $site->title() ?>
                    <img src="<?= url('assets/img/pedalades-logo.svg') ?>" alt="logo">
                </a>
            </nav>
        </div>
    </header>

    <!-- Main content -->
    <main class="mx-auto px-4 py-12">
