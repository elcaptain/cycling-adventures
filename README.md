# Cycling Adventures Blog

A modern cycling blog built with Kirby CMS, featuring interactive maps, GPX track visualization, and photo galleries with GPS integration.

## Features

- **Adventure Posts**: Create detailed cycling adventure posts with ride statistics
- **GPX Track Integration**: Automatically extract ride statistics from GPX files
- **Interactive Maps**: Display routes on interactive Leaflet maps
- **Photo Mapping**: Photos with GPS EXIF data are automatically placed on the map
- **Flexible Content**: Use blocks to arrange text and images differently for each post
- **Responsive Design**: Built with Tailwind CSS for all screen sizes
- **Git Version Control**: Manage your site code with Git/GitHub
- **FTP Deployment**: One-command deployment to any PHP hosting via `deploy.sh` (lftp)

## Project Structure

```
cycling-adventures/
├── assets/
│   ├── css/              # Compiled CSS (generated, not in git)
│   ├── scss/             # Source SCSS files
│   └── js/               # JavaScript files
│       ├── adventure-map.js   # Map initialization
│       └── vendor/       # Third-party libraries (Leaflet.js)
├── content/              # Your adventure content (not in git by default)
│   ├── home/             # Homepage
│   └── adventures/       # Adventure posts
├── kirby/                # Kirby CMS core files
├── site/
│   ├── blueprints/       # Content structure definitions
│   ├── config/           # Site configuration
│   ├── plugins/          # Custom plugins (GPX/EXIF helpers)
│   ├── snippets/         # Reusable template parts
│   └── templates/        # Page templates
├── package.json          # Node.js dependencies and build scripts
├── deploy.sh             # FTP deployment script (see Deployment section)
├── deploy.conf.example   # Template for deploy.sh credentials
├── deploy.conf           # Your real FTP credentials (gitignored, not in git)
└── README.md             # This file
```

## Prerequisites

- **PHP 8.0+** (for local development and server)
- **Node.js 16+** and npm (for building CSS)
- **Git** (for version control)
- **lftp** (for deployment via `deploy.sh`) — install with `brew install lftp`

## Installation & Setup

### 1. Clone or Download This Repository

```bash
git clone <your-repo-url> cycling-adventures
cd cycling-adventures
```

### 2. Install Node Dependencies

```bash
npm install
```

This will install:
- Tailwind CSS
- Sass compiler
- PostCSS with Autoprefixer

### 3. Build CSS

Build the CSS files from SCSS source:

```bash
npm run build
```

For development with auto-rebuild on changes:

```bash
npm run dev
```

### 4. Set Up Kirby Panel (Admin)

The Kirby Panel is where you'll create and manage your adventures.

1. Start a local PHP server:

```bash
php -S localhost:8000 kirby/router.php
```

2. Open http://localhost:8000/panel in your browser

3. Create your admin account:
   - Email: your@email.com
   - Password: (choose a strong password)
   - Language: English

### 5. Update Configuration

Edit `site/config/config.php`:

- Change `'debug' => true` to `'debug' => false` for production
- Update `'url'` to your production URL

## Creating Your First Adventure

### Via the Kirby Panel (Recommended)

1. Go to http://localhost:8000/panel
2. Navigate to "Adventures"
3. Click "+ Add" to create a new adventure
4. Fill in the fields:
   - **Title**: Name of your ride
   - **Date**: When the ride took place
   - **Description**: Brief summary (shown on homepage)
   - **GPX File**: Upload your .gpx track file
   - **Photos**: Upload photos from the ride
   - **Ride Statistics**: Auto-filled from GPX, or enter manually
   - **Content Layout**: Use blocks to tell your story

5. Click "Save" and then "Change status" → "Published"

### GPX File Tips

- Export GPX files from Strava, Komoot, Garmin Connect, etc.
- The system will automatically extract:
  - Total distance
  - Elevation gain
  - Duration
  - Average speed
  - Track points for map visualization

### Photo Tips

- Photos with GPS EXIF data will be placed on the map automatically
- Photos without GPS will be matched to track points by timestamp
- For best results, ensure your camera/phone has GPS enabled
- Recommended formats: JPG with EXIF data

## Development Workflow

### Local Development

1. Start PHP server:
```bash
php -S localhost:8000 kirby/router.php
```

2. In another terminal, watch for CSS changes:
```bash
npm run dev
```

3. Open http://localhost:8000 in your browser
4. Edit templates, styles, or content
5. Refresh browser to see changes

### Building for Production

Before deploying, always build production CSS:

```bash
npm run build
```

This creates minified CSS in `assets/css/style.css`.

## Version Control with Git

### Initial Commit

The project is already initialized with Git. Make your first commit:

```bash
git add .
git commit -m "Initial commit: Cycling Adventures blog setup"
```

### Connect to GitHub

1. Create a new repository on GitHub (free plan)
2. Don't initialize it with README (we already have one)
3. Connect your local repo:

```bash
git remote add origin https://github.com/YOUR-USERNAME/cycling-adventures.git
git branch -M main
git push -u origin main
```

### What's in Git?

The `.gitignore` file excludes:
- `content/` - Your adventures (managed via Panel)
- `node_modules/` - Node dependencies
- `assets/css/style.css` - Compiled CSS
- `media/` - Generated thumbnails
- `site/accounts/` - User accounts
- `site/sessions/` - Session data
- `site/cache/` - Cache files

**Why exclude content?**
Content is managed through the Kirby Panel and deployed via FTP. This keeps your Git repo focused on code, not content files.

If you prefer to version control content, remove the `/content/*` lines from `.gitignore`.

### Regular Workflow

```bash
# Make changes to code/templates
git add .
git commit -m "Description of changes"
git push
```

## Deployment via FTP

Deployment is handled by `deploy.sh`, which builds the CSS, patches a copy of
`site/config/config.php` for production, and syncs only new/changed files to
your server over FTP with `lftp mirror --only-newer`. Your local
`site/config/config.php` is never modified — it stays on `debug: true` and
your local URL.

### One-Time Setup

1. Install `lftp`:
```bash
brew install lftp
```

2. Create your deploy config from the example:
```bash
cp deploy.conf.example deploy.conf
```

3. Edit `deploy.conf` and fill in your details:
```bash
FTP_HOST=ftp.yourhost.com
FTP_USER=your-ftp-username
FTP_PASS=your-ftp-password
FTP_PORT=21
REMOTE_PATH=/httpdocs        # path on the server where index.php lives
PRODUCTION_URL=https://www.yourdomain.com
```

`deploy.conf` is gitignored — it holds real credentials and must never be committed.

### Running a Deployment

```bash
./deploy.sh
```

This will:
1. Run `npm run build` to produce production CSS
2. Generate a temporary copy of `site/config/config.php` with `debug => false`,
   `url` set to `PRODUCTION_URL`, and the license `active` flag turned on
3. Mirror `assets/` (excluding `assets/scss/`), `kirby/`, and `site/`
   (excluding `cache/`, `sessions/`, `accounts/`, `config.php`) to the server,
   uploading only files that are new or changed
4. Upload `index.php`, `.htaccess`, and the patched production `config.php`

Only the files the site actually needs to run are touched on the server —
`node_modules/`, `.git/`, `README.md`, `composer.json`, and other local-only
files are never synced.

**First deployment only:** `content/` isn't uploaded by `deploy.sh`. Upload it
once manually via FTP, then manage content through the Panel on the server
from then on.

### Post-Deployment Steps

1. Visit http://yourdomain.com/panel
2. Log in with your admin credentials
3. If this is first deployment, create your account
4. Start creating adventures!

### Deployment Checklist

- [ ] `deploy.conf` is set up (one-time)
- [ ] Run `./deploy.sh`
- [ ] First deployment only: upload `content/` manually via FTP
- [ ] Check file permissions (see below)
- [ ] Test the site in a browser
- [ ] Test the Panel at /panel

### File Permissions

On your server, these directories need write permissions (usually 755 or 775):

```bash
chmod 755 content/
chmod 755 media/
chmod 755 site/accounts/
chmod 755 site/cache/
chmod 755 site/sessions/
```

## Customization

### Colors & Styling

Edit `tailwind.config.js` to customize colors:

```javascript
colors: {
  'ride-primary': '#2563eb',    // Links and track color
  'ride-secondary': '#16a34a',  // Elevation/success color
  'ride-accent': '#ea580c',     // Highlights
}
```

Edit `assets/scss/style.scss` for custom styles.

After changes, rebuild:
```bash
npm run build
```

### Templates

Templates are in `site/templates/`:
- `home.php` - Homepage with adventure list
- `adventure.php` - Single adventure page
- `adventures.php` - Adventures parent page

Snippets (reusable parts) in `site/snippets/`:
- `header.php` - HTML head and site header
- `footer.php` - Site footer
- `adventure-card.php` - Adventure card for list view

### Blueprints

Content structure is defined in `site/blueprints/pages/`:
- `home.yml` - Homepage fields
- `adventures.yml` - Adventures parent
- `adventure.yml` - Individual adventure fields

Edit these to add/remove fields in the Panel.

### Map Customization

Edit `assets/js/adventure-map.js` to customize:
- Map tile provider (currently OpenStreetMap)
- Marker icons and colors
- Track line styling
- Popup content

## Troubleshooting

### CSS Not Loading

1. Make sure you ran `npm install`
2. Run `npm run build`
3. Check that `assets/css/style.css` exists

### Panel Not Accessible

1. Check `.htaccess` was uploaded (Apache)
2. Verify `site/config/config.php` has correct URL
3. Check PHP version (needs 8.0+)

### Photos Not Showing on Map

1. Verify photos have GPS EXIF data
2. Check that GPX file has timestamps
3. Photos will only appear if within 5 minutes of track time

### GPX Data Not Extracting

1. Verify GPX file is valid XML
2. Check file uploaded correctly in Panel
3. Ensure PHP has XML support enabled

### Permission Errors

Set correct permissions on server:
```bash
chmod -R 755 content/ media/ site/accounts/ site/cache/ site/sessions/
```

## Useful Commands

```bash
# Development
npm run dev          # Watch and rebuild CSS on changes
php -S localhost:8000 kirby/router.php  # Start local PHP server

# Build
npm run build        # Build production CSS

# Deploy
./deploy.sh          # Build, patch config, and sync changed files to production via FTP

# Git
git status           # Check what changed
git add .            # Stage all changes
git commit -m "msg"  # Commit changes
git push             # Push to GitHub
git pull             # Pull latest from GitHub
```

## Technologies Used

- **[Kirby CMS](https://getkirby.com)** - File-based CMS
- **[Tailwind CSS](https://tailwindcss.com)** - Utility-first CSS framework
- **[Leaflet.js](https://leafletjs.com)** - Interactive maps
- **[Sass](https://sass-lang.com)** - CSS preprocessor
- **PHP 8+** - Server-side language
- **Git/GitHub** - Version control

## License

This project is open source and available under the MIT License.

## Support

For Kirby CMS questions: https://getkirby.com/docs
For issues with this project: Check GitHub Issues

## Credits

Built with Kirby CMS. Maps powered by Leaflet.js and OpenStreetMap.

---

Happy cycling! 🚴‍♂️
