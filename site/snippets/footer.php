    </main>

    <!-- Footer -->
    <footer class="bg-accent-900 text-accent-200 mt-24">
        <div class="container mx-auto px-4 py-8">
            <div class="text-center">
                <p>&copy; <?= date('Y') ?> <?= $site->title() ?>. Built with Kirby CMS.</p>
            </div>
        </div>
    </footer>

    <?php if ($page->template()->name() === 'adventure'): ?>
    <!-- Leaflet JS -->
    <script src="<?= url('assets/js/vendor/leaflet/leaflet.js') ?>"></script>

    <!-- Chart.js -->
    <script src="<?= url('assets/js/vendor/chartjs/chart.min.js') ?>"></script>

    <!-- Custom JS -->
    <script src="<?= url('assets/js/adventure-map.js') ?>"></script>
    <script src="<?= url('assets/js/elevation-profile.js') ?>"></script>
    <script src="<?= url('assets/js/lightbox.js') ?>"></script>
    <?php endif ?>

    <?php if ($page->template()->name() === 'collection'): ?>
    <!-- Leaflet JS -->
    <script src="<?= url('assets/js/vendor/leaflet/leaflet.js') ?>"></script>

    <!-- Chart.js -->
    <script src="<?= url('assets/js/vendor/chartjs/chart.min.js') ?>"></script>

    <!-- Custom JS (elevation-profile.js is intentionally excluded — collection-cards.js handles per-card charts) -->
    <script src="<?= url('assets/js/adventure-map.js') ?>"></script>
    <script src="<?= url('assets/js/collection-cards.js') ?>"></script>
    <script src="<?= url('assets/js/lightbox.js') ?>"></script>
    <?php endif ?>

</body>
</html>
