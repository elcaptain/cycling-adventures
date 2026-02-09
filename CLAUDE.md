# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Kirby CMS (v5.2.2) blog for cycling adventures with interactive maps, GPX track visualization, and photo galleries with GPS integration. Content is managed via the Kirby Panel and deployed via FTP.

## Essential Development Commands

### Starting the Development Server

**CRITICAL**: Always use Kirby's router when running the PHP built-in server:

```bash
php -S localhost:8000 kirby/router.php
```

Without `kirby/router.php`, media routes won't work and thumbnails will fail with 404 errors.

### Building CSS

```bash
# Install dependencies first
npm install

# Build production CSS (minified)
npm run build

# Watch and rebuild on changes (development)
npm run dev
```

The build process:
1. Compiles SCSS to CSS (`assets/scss/style.scss` → `assets/css/style-temp.css`)
2. Processes with Tailwind CSS and minifies
3. Output: `assets/css/style.css` (excluded from git)

## Architecture

### GPX and Photo Processing

The core feature is automatic extraction of ride statistics from GPX files and matching photos to GPS coordinates.

**Location**: `site/plugins/adventure-helpers/index.php`

This plugin adds three page methods to adventure pages:

1. **`$page->gpxData()`** - Parses GPX file and returns:
   - `stats`: distance, elevation gain, duration, moving time, average speed
   - `trackpoints`: array of GPS coordinates with timestamps
   - `bounds`: bounding box for map display

2. **`$page->photosWithExif()`** - Extracts EXIF data from photos:
   - GPS coordinates (if embedded)
   - Timestamps
   - Falls back to Kirby file fields if EXIF missing

3. **`$page->matchedPhotos()`** - Matches photos to GPX track points:
   - If photo has GPS in EXIF, uses that
   - Otherwise matches by timestamp (within 5 minutes of track time)
   - Returns photos with coordinates for map markers

**Key functions**:
- `parseGPX()` - XML parsing, calculates distance using Haversine formula
- `getPhotoExif()` - Reads EXIF data, converts GPS coordinates to decimal degrees
- `matchPhotosToTrack()` - Timestamp-based photo-to-track matching

### GPX File Upload Configuration

GPX files require special handling in Kirby since it's not a default file type.

**Configuration** (`site/config/config.php`):
- Hook `file.create:before` bypasses MIME validation for `.gpx` files
- Component `file::type` registers GPX as a document type
- File blueprint `site/blueprints/files/gpx.yml` defines upload behavior

**Important**: Don't add strict MIME type validation for GPX - files may be detected as `text/xml`, `application/xml`, or `application/gpx+xml` depending on the system.

### Template Structure

**Main Templates** (`site/templates/`):
- `home.php` - Paginated list of adventures (9 per page)
- `adventure.php` - Single adventure with map, stats, photos, and flexible content blocks
- `adventures.php` - Parent page (redirects to home)

**Snippets** (`site/snippets/`):
- `header.php` - HTML head, loads CSS and Leaflet.js
- `footer.php` - Closing tags, loads JavaScript
- `adventure-card.php` - Card component for adventure list

**Data Flow in Templates**:
```php
// In adventure.php
$gpxData = $page->gpxData();        // Get GPX statistics and trackpoints
$matchedPhotos = $page->matchedPhotos(); // Get photos with GPS coordinates

// Pass to JavaScript via data attributes
<div id="adventure-map"
     data-trackpoints="<?= esc(json_encode($trackpoints), 'attr') ?>"
     data-photos="<?= esc(json_encode($matchedPhotos), 'attr') ?>">
</div>
```

### Interactive Maps

**Location**: `assets/js/adventure-map.js`

Leaflet.js initialization:
- Reads GPX trackpoints and photo data from `data-*` attributes
- Draws polyline for the route
- Adds start/finish markers
- Places photo markers with popup thumbnails
- Auto-fits map bounds to show entire route

**Leaflet Library**: Self-hosted in `assets/js/vendor/leaflet/` (no CDN dependencies)

### Blueprints and Content Structure

**Blueprint** (`site/blueprints/pages/adventure.yml`):
- Fields: title, date, description, GPX file, ride statistics, photos, flexible layout blocks
- Uses `type: headline` to visually group fields (not `type: fields` which causes errors)
- GPX file field uses `template: gpx` to apply the GPX file blueprint
- Photos field uses default template for images

**Content Storage**:
- Adventures stored in `content/1_adventures/`
- Each adventure is a numbered folder (e.g., `1_example-mountain-ride/`)
- Contains `adventure.txt` with field data and uploaded files (GPX, photos)

## Common Issues and Solutions

### Media/Thumbnails Not Working

**Symptom**: 404 errors for images in Panel or frontend

**Cause**: Server started without router

**Solution**: Always start with `php -S localhost:8000 kirby/router.php`

### GPX Upload Errors

**Symptom**: "Invalid file type" or "Invalid MIME type" errors

**Root Cause**: GPX files may be detected with different MIME types

**Current Solution**:
- Hook in config bypasses MIME validation for `.gpx` extension
- File blueprint accepts by extension only, not MIME type
- Never add strict MIME type restrictions

### Blueprint Field Errors

**Symptom**: "Invalid field type (fields)" in Panel

**Cause**: Using `type: fields` for grouping (deprecated/incorrect syntax)

**Solution**: Use `type: headline` with `numbered: false` for visual section headers

### CSS Build Errors with `prose` Class

**Symptom**: "The `prose` class does not exist"

**Cause**: Missing Tailwind Typography plugin

**Solution**: Ensure `@tailwindcss/typography` is in `package.json` devDependencies and added to `tailwind.config.js` plugins array

## Deployment

Build CSS locally:
```bash
npm run build
```

Upload via FTP:
- `assets/` - CSS, JS, images
- `kirby/` - CMS core
- `site/` - Templates, blueprints, plugins, config
- `index.php` and `.htaccess`
- `content/` (first deployment only, then manage via Panel on server)

**Never upload**: `node_modules/`, `assets/scss/`, `.git/`

## Configuration Notes

- **Debug mode**: Set `'debug' => false` in production (`site/config/config.php`)
- **URL**: Update `'url'` in config to match production domain
- **Thumbs driver**: Uses GD (`'driver' => 'gd'`) - ensure GD extension enabled in PHP
- **File permissions**: Ensure writable: `content/`, `media/`, `site/accounts/`, `site/cache/`, `site/sessions/`

## Key Kirby CMS Concepts

- **Page Methods**: Custom functions available on page objects (like `$page->gpxData()`)
- **File Blueprints**: Define upload validation and metadata fields for specific file types
- **Hooks**: Execute code before/after Kirby events (e.g., `file.create:before`)
- **Components**: Override Kirby's core functionality (e.g., file type detection)
- **Blocks**: Flexible content layout system used in adventure stories
