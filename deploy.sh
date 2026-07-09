#!/usr/bin/env bash
#
# Deploy changed files to the production webspace over FTP.
# Uploads only new/changed files (lftp mirror --only-newer) and swaps
# site/config/config.php to production settings for the upload only —
# your local working copy is never touched.
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

for var in FTP_HOST FTP_USER FTP_PASS FTP_PORT REMOTE_PATH PRODUCTION_URL; do
    if [[ -z "${!var:-}" ]]; then
        echo "deploy.conf is missing a value for $var" >&2
        exit 1
    fi
done

echo "==> Building CSS"
npm run build

echo "==> Preparing production config"
TMP_CONFIG="$(mktemp)"
trap 'rm -f "$TMP_CONFIG"' EXIT

sed \
    -e "s|'debug' => true,|'debug' => false,|" \
    -e "s|'url' => 'http://jrmacbook.local:8000',.*|'url' => '${PRODUCTION_URL}',|" \
    -e "s|'active' => false, // Set to true in production|'active' => true,|" \
    site/config/config.php > "$TMP_CONFIG"

if diff -q site/config/config.php "$TMP_CONFIG" >/dev/null; then
    echo "Warning: production config patch made no changes — check the sed patterns in deploy.sh still match config.php" >&2
fi

echo "==> Syncing changed files to ${FTP_HOST}${REMOTE_PATH}"
lftp -u "${FTP_USER},${FTP_PASS}" -p "${FTP_PORT}" "${FTP_HOST}" <<EOF
set ftp:ssl-allow true
set ssl:verify-certificate no
mirror --reverse --only-newer --parallel=4 --verbose \
    --exclude-glob node_modules/ \
    --exclude-glob .git/ \
    --exclude-glob assets/scss/ \
    --exclude-glob content/ \
    --exclude-glob site/cache/ \
    --exclude-glob site/sessions/ \
    --exclude-glob site/accounts/ \
    --exclude-glob site/config/config.php \
    --exclude-glob deploy.conf \
    --exclude-glob deploy.sh \
    --exclude-glob "*.md" \
    ./ ${REMOTE_PATH}/
put "$TMP_CONFIG" -o ${REMOTE_PATH}/site/config/config.php
bye
EOF

echo "==> Done. Local site/config/config.php is unchanged (still debug=true, local URL)."
