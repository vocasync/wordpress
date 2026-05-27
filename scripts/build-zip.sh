#!/usr/bin/env bash
#
# Build a distributable plugin ZIP (production deps only). Produces
# build/vocasync.zip with a top-level `vocasync/` folder, ready to upload via
# Plugins → Add New → Upload, or to drop into wp-content/plugins/.
#
# Requires: composer, zip. (CI/release use.)
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
STAGE="$ROOT/build/vocasync"

rm -rf "$ROOT/build"
mkdir -p "$STAGE"

# Runtime files only — no tests, tooling, or dev config.
cp -r "$ROOT"/vocasync.php "$ROOT"/src "$ROOT"/assets "$ROOT"/languages \
	"$ROOT"/readme.txt "$ROOT"/LICENSE "$ROOT"/composer.json "$ROOT"/composer.lock "$STAGE/"

( cd "$STAGE" && composer install --no-dev --optimize-autoloader --no-interaction )

# Drop composer manifests from the shipped artifact (vendor/ is now built).
rm -f "$STAGE/composer.json" "$STAGE/composer.lock"

( cd "$ROOT/build" && zip -rq vocasync.zip vocasync \
	-x '*/.DS_Store' -x '*/.git/*' )

echo "Built: $ROOT/build/vocasync.zip"
