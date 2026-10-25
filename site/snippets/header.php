<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page->title() ?> | <?= $site->title() ?></title>

    <!-- Meta description -->
    <?php if ($page->description()->isNotEmpty()): ?>
    <meta name="description" content="<?= $page->description() ?>">
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
