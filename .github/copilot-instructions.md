## Purpose

This is a Kirby CMS site for cycling adventures (PHP 8+, Tailwind, Sass, Leaflet). These instructions help AI coding agents be productive by describing the architecture, workflows, and repo-specific patterns.

**Big picture**
- Kirby core lives in `kirby/`; content is file-based under `content/` and managed via the Panel.
- Frontend assets: source SCSS and JS live in `assets/scss/` and `assets/js/`; compiled CSS is `assets/css/style.css` (generated).
- Site logic, templates, snippets, blueprints and plugins are under `site/`. GPX/photo helpers live in `site/plugins/` (see `site/plugins/adventure-helpers/`).

**Key developer workflows**
- Install: `npm install` and `composer install`.
- CSS pipeline: `npm run dev` (watch) and `npm run build` (production). `build:css` uses `sass` -> `tailwindcss` -> `assets/css/style.css` (see `package.json`).
- PHP dev server: `composer start` (runs `php -S localhost:8000 kirby/router.php`) or run the same `php -S` command directly.

**Project conventions**
- Do not commit `content/` (site content is managed via Panel). Avoid committing `media/` derivate files and `assets/css/style.css` (compiled output).
- Keep templates minimal; place heavier logic in `site/plugins/` helpers or controllers.
- Use existing snippets for UI consistency (e.g., `site/snippets/adventure-card.php`).

**Important files to inspect (examples)**
- Frontend: `package.json`, `assets/scss/style.scss`, `tailwind.config.js`, `postcss.config.js`.
- Map & JS: `assets/js/adventure-map.js`, `assets/js/vendor/leaflet/`.
- Backend/site: `site/plugins/adventure-helpers/`, `site/blueprints/`, `site/templates/adventure.php`, `site/snippets/`.

**Integration points & external deps**
- Leaflet (maps) initialized in `assets/js/adventure-map.js` with vendor files under `assets/js/vendor/leaflet/`.
- GPX parsing and EXIF matching implemented in `site/plugins/` — inspect those plugins before changing GPX logic.
- Runtime: PHP 8+, Composer packages in `composer.json`; Node tooling for CSS build (Tailwind, Sass).

**Deployment notes**
- Before deploy run `npm run build` and set `'debug' => false` and update `'url'` in `site/config/config.php`.
- Server needs write permissions for `content/`, `media/`, `site/cache/`, etc.; do not upload `node_modules/`.

If anything is unclear or you want more detail (tests, CI, commit style), tell me what to expand.
