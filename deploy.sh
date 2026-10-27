#!/usr/bin/env bash
#
# Deploy changed files to the production webspace over FTP.
# Uploads only new/changed files (lftp mirror --only-newer).
# site/config/config.php switches to production settings on its own
# when it isn't running under the local dev server.
#
# Setup: cp deploy.conf.example deploy.conf and fill in your details.

set -euo pipefail
cd "$(dirname "$0")"

if ! command -v lftp >/dev/null 2>&1; then
    echo "lftp is not installed. Install it with: brew install lftp" >&2
    exit 1
fi

if [[ ! -f deploy.conf ]]; then
    echo "deploy.conf not found. Copy deploy.conf.example to deploy.conf and fill in your details." >&2
    exit 1
fi

source deploy.conf

for var in FTP_HOST FTP_USER FTP_PASS FTP_PORT REMOTE_PATH; do
    if [[ -z "${!var:-}" ]]; then
        echo "deploy.conf is missing a value for $var" >&2
        exit 1
    fi
done

echo "==> Building CSS"
npm run build

echo "==> Syncing changed files to ${FTP_HOST}${REMOTE_PATH}"
# Whitelist of what the site actually needs to run — everything else
# (tools/, node_modules/, .git, README.md, composer.json, configs, etc.)
# is never touched on the server.
lftp -u "${FTP_USER},${FTP_PASS}" -p "${FTP_PORT}" "${FTP_HOST}" <<EOF
set ftp:ssl-allow true
set ssl:verify-certificate no
mirror --reverse --only-newer --parallel=4 --verbose \
    --exclude-glob .DS_Store \
    --exclude-glob assets/scss/ \
    --exclude-glob style-temp.css \
    assets/ ${REMOTE_PATH}/assets/
mirror --reverse --only-newer --parallel=4 --verbose \
    --exclude-glob .DS_Store \
    kirby/ ${REMOTE_PATH}/kirby/
mirror --reverse --only-newer --parallel=4 --verbose \
    --exclude-glob .DS_Store \
    --exclude-glob cache/ \
    --exclude-glob sessions/ \
    --exclude-glob accounts/ \
    site/ ${REMOTE_PATH}/site/
put index.php -o ${REMOTE_PATH}/index.php
put .htaccess -o ${REMOTE_PATH}/.htaccess
bye
EOF

echo "==> Done."
