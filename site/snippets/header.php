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

    <!-- Styles -->
    <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">

    <!-- Leaflet CSS (for maps) -->
    <link rel="stylesheet" href="<?= url('assets/js/vendor/leaflet/leaflet.css') ?>">

    <!-- Favicon (optional - add your own) -->
    <!-- <link rel="icon" type="image/svg+xml" href="/favicon.svg"> -->
</head>
<body class="bg-gray-50 text-gray-900">

    <!-- Header -->
    <header class="bg-white shadow-sm">
        <div class="container mx-auto px-4 py-6">
            <nav class="flex items-center justify-between">
                <a href="<?= $site->url() ?>" class="text-2xl font-bold text-ride-primary hover:text-ride-secondary transition-colors">
                    <?= $site->title() ?>
                </a>

                <!-- Navigation -->
                <ul class="flex gap-6">
                    <li>
                        <a href="<?= $site->url() ?>" class="text-gray-700 hover:text-ride-primary transition-colors">
                            Adventures
                        </a>
                    </li>
                    <!-- Add more navigation items if needed -->
                </ul>
            </nav>
        </div>
    </header>

    <!-- Main content -->
    <main class="container mx-auto px-4 py-12">
