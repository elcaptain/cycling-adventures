## Purpose

This repository is a Kirby CMS site for cycling adventures (PHP + Tailwind + Leaflet). The goal of these instructions is to help AI coding agents become immediately productive by documenting the project's structure, developer workflows, conventions, and integration points.

## Big picture (why & architecture)
- Kirby CMS (core in `kirby/`) serves PHP templates and the Panel; content is file-based in `content/`.
- Frontend assets (styles and JS) live in `assets/` and are built with Node/Tailwind/Sass.
- Site-specific logic and blueprints live under `site/` (templates, snippets, blueprints, plugins).
- GPX and photo EXIF handling are implemented as site plugins/helpers (see `site/plugins/` and `site/config/`).

## Key workflows & commands
- Install node deps: `npm install` (see `package.json`).
- Build production CSS: `npm run build` (runs `build:css` which uses `sass` + `tailwindcss`).
- Dev CSS watch: `npm run dev` (build:css --watch) or `npm run watch:css`.
- Start PHP dev server: `composer start` (runs `php -S localhost:8000 kirby/router.php`) or `php -S localhost:8000 kirby/router.php` directly.
- Composer requires PHP 8.2+ and `getkirby/cms` (see `composer.json`).

## Project-specific conventions
- `content/` is intentionally excluded from Git (managed via the Kirby Panel); avoid committing user content unless explicitly requested.
- Compiled CSS (`assets/css/style.css`) is generated and typically not committed. Source is `assets/scss/style.scss` and Tailwind config is `tailwind.config.js`.
- Media thumbnails are generated into `media/` at runtime; do not commit.

## Important files to inspect when making changes
- Frontend & build: `package.json`, `assets/scss/style.scss`, `tailwind.config.js`, `postcss.config.js`.
- Map & JS: `assets/js/adventure-map.js` (Leaflet init and popup/marker logic), `assets/js/vendor/leaflet/`.
- Templates/snippets: `site/templates/adventure.php`, `site/snippets/adventure-card.php`, `site/templates/adventures.php`, `site/templates/home.php`.
- Blueprints: `site/blueprints/` (add/remove fields here to change Panel forms).
- Config & plugins: `site/config/` and `site/plugins/` (GPX parsing, EXIF helpers live here).

## Integration points & external deps
- Leaflet maps: JS in `assets/js/adventure-map.js` and vendor files under `assets/js/vendor/leaflet/`.
- GPX handling: look for plugin code under `site/plugins/` (automated extraction of distance/elevation/time occurs here).
- Tailwind + Sass pipeline: `sass` builds a temp CSS, then `tailwindcss` reads that and outputs `assets/css/style.css` (see `package.json` `build:css`).

## Deployment notes agents should respect
- Before deploying, run: `npm run build` and set `'debug' => false` in `site/config/config.php` and update `'url'`.
- Server requirements: PHP 8.0+ (composer.json requests 8.2+), write permissions for `content/`, `media/`, `site/accounts/`, `site/cache/`, `site/sessions/`.
- Typical deployment method for this project: FTP upload of `assets/`, `kirby/`, `site/`, `index.php`, and `.htaccess`. Do not upload `node_modules/` or development-only files.

## Code patterns and expectations
- Keep PHP templates minimal: heavy logic belongs in `site/plugins/` helpers or controllers.
- Use existing snippets (e.g., `adventure-card.php`) for consistent UI; prefer editing snippets over duplicating markup.
- Blueprint field names are authoritative for Panel forms — changing field names requires template and plugin updates.

## When editing tests/builds or adding features
- Update `package.json` scripts if adding front-end tools; document new scripts in `README.md`.
- If a feature touches GPX/photo matching, update the plugin(s) under `site/plugins/` and add example GPX/EXIF files to `content/_drafts/` for local testing only.

## Quick reference (commands)
- `npm install`
- `npm run dev` (watch CSS)
- `npm run build` (production CSS)
- `composer start` or `php -S localhost:8000 kirby/router.php`

If anything important is missing or you want agent behaviour tuned (e.g., commit style, tests, or CI), tell me which areas to expand.
