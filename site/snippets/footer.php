    </main>

    <!-- Footer -->
    <footer class="bg-gray-900 text-gray-400 mt-24">
        <div class="container mx-auto px-4 py-8">
            <div class="text-center">
                <p>&copy; <?= date('Y') ?> <?= $site->title() ?>. Built with Kirby CMS.</p>
            </div>
        </div>
    </footer>

    <!-- Leaflet JS -->
    <script src="<?= url('assets/js/vendor/leaflet/leaflet.js') ?>"></script>

    <!-- Custom JS -->
    <script src="<?= url('assets/js/adventure-map.js') ?>"></script>

</body>
</html>
