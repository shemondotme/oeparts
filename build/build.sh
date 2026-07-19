#!/usr/bin/env bash
#
# OeParts release build (Module 21, Chunk 5.1) — LOCAL / CI ONLY.
# Production never runs this (no Node/npm on production, rules #18/#19).
#
# Produces:  dist/oeparts-<version>.zip  +  its sha256
#
# Pipeline:
#   1. Clean export of the committed tree (git archive → no .git, no untracked cruft)
#   2. composer install --no-dev -o        (production dependencies only)
#   3. npm ci && npm run build             (compile public/build — local/CI only)
#   4. php artisan oeparts:build           (strip dev files, bundle licenses, sha256 manifest)
#   5. zip + sha256                        (the distributable release artefact)
#
# Requires (CI / Git Bash): git, composer, php, npm, tar, zip, sha256sum.
# Usage:  bash build/build.sh            # builds the current HEAD
#         (GitHub Actions checks out the v*.*.* tag first — Chunk 5.2)

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DIST="$ROOT/dist"
EXPORT="$DIST/export"

VERSION="$(php -r '$m=json_decode(file_get_contents($argv[1]),true); echo $m["version"] ?? "0.0.0";' "$ROOT/version.json")"
ZIP="$DIST/oeparts-$VERSION.zip"

echo "==> Building OeParts v$VERSION"

# 1. Clean export of tracked files (respects .gitignore, excludes .git).
rm -rf "$EXPORT" "$ZIP"
mkdir -p "$EXPORT"
git -C "$ROOT" archive HEAD | tar -x -C "$EXPORT"

# A transient .env so composer's post-autoload-dump `artisan package:discover` can boot.
# Use .env.testing (array/sqlite drivers, APP_ENV=testing), same as tests.yml — NOT
# .env.example, which sets APP_ENV=production + CACHE_STORE=redis and would need a
# live Redis/MySQL connection just to let the app boot for discovery/build commands.
# Stripped from the shipped zip by oeparts:build in step 4 (config('updates.build.exclude')).
cp "$ROOT/.env.testing" "$EXPORT/.env"

# 2. Production dependencies (no dev, optimised autoloader).
composer install --no-dev --optimize-autoloader --no-interaction --working-dir="$EXPORT"

# 3. Front-end assets (Node is local/CI only — never shipped/run on production).
if [ -f "$EXPORT/package.json" ]; then
  npm --prefix "$EXPORT" ci
  npm --prefix "$EXPORT" run build
fi

# 4. Strip dev files + bundle third-party licenses + write the per-file sha256 manifest.
#    Run the SOURCE repo's artisan against the export dir as data (the export need not boot).
php "$ROOT/artisan" oeparts:build --path="$EXPORT"

# 5. Zip the export contents at the archive root (so extract yields app/, vendor/, … at top).
( cd "$EXPORT" && zip -rq "$ZIP" . )

SHA="$(sha256sum "$ZIP" | awk '{print $1}')"
SIZE="$(wc -c < "$ZIP" | tr -d ' ')"

echo "==> Built $ZIP"
echo "    sha256: $SHA"
echo "    bytes:  $SIZE"

# Emit the release facts for the packaging step (Chunk 5.2) to fold into version.json.
printf '{"version":"%s","sha256":"%s","size_bytes":%s}\n' "$VERSION" "$SHA" "$SIZE" > "$DIST/build-result.json"
