#!/usr/bin/env bash
#
# Builds a minimal TYPO3 instance under $INSTANCE_DIR with the plan2net/webp
# extension wired as a path repository against the current branch. Heavy/slow
# step — designed to be cached in CI keyed on the extension code hash.
#
# Inputs (env):
#   TYPO3_VERSION       Composer constraint for typo3/cms-core. Default: ^14.3
#   INSTANCE_DIR        Where to build. Default: /tmp/plan2net-webp-e2e/instance
#   EXTENSION_DIR       Path to this extension. Default: parent of this script's grandparent
#
set -euo pipefail

TYPO3_VERSION="${TYPO3_VERSION:-^14.3}"
INSTANCE_DIR="${INSTANCE_DIR:-/tmp/plan2net-webp-e2e/instance}"

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
EXTENSION_DIR="${EXTENSION_DIR:-$(cd "$script_dir/../.." && pwd)}"

echo "[bootstrap] TYPO3 ${TYPO3_VERSION}, extension at ${EXTENSION_DIR}, instance at ${INSTANCE_DIR}"

rm -rf "$INSTANCE_DIR"
mkdir -p "$INSTANCE_DIR"
cd "$INSTANCE_DIR"

cat > composer.json <<JSON
{
    "name": "plan2net/webp-e2e-instance",
    "description": "Throwaway TYPO3 instance for E2E testing of plan2net/webp",
    "type": "project",
    "repositories": [
        { "type": "path", "url": "${EXTENSION_DIR}", "options": {"symlink": false} }
    ],
    "require": {
        "typo3/cms-core": "${TYPO3_VERSION}",
        "typo3/cms-backend": "${TYPO3_VERSION}",
        "typo3/cms-frontend": "${TYPO3_VERSION}",
        "typo3/cms-install": "${TYPO3_VERSION}",
        "typo3/cms-fluid-styled-content": "${TYPO3_VERSION}",
        "plan2net/webp": "@dev",
        "jcupitt/vips": "^2.6"
    },
    "minimum-stability": "dev",
    "prefer-stable": true,
    "extra": {
        "typo3/cms": {
            "web-dir": "public"
        }
    },
    "config": {
        "allow-plugins": {
            "typo3/cms-composer-installers": true,
            "typo3/class-alias-loader": true
        }
    }
}
JSON

export TYPO3_SKIP_ASSET_PUBLISH=1
composer install --no-interaction --no-progress --prefer-dist

vendor/bin/typo3 setup \
    --driver=sqlite \
    --admin-username=admin \
    --admin-user-password=Password123! \
    --admin-email=admin@example.com \
    --project-name="plan2net/webp E2E" \
    --create-site=http://localhost/ \
    --server-type=other \
    --no-interaction

# Relax checks that block non-interactive CLI runs in CI; pin the GFX
# processor so FAL actually creates ProcessedFiles when the FE renders
# IMG_RESOURCE — without that, AfterFileProcessing never fires and our
# sibling-generation listener has nothing to react to.
mkdir -p public/typo3conf
cat > config/system/additional.php <<'ADDITIONAL'
<?php
$GLOBALS['TYPO3_CONF_VARS']['BE']['passwordPolicy'] = '';
$GLOBALS['TYPO3_CONF_VARS']['SYS']['trustedHostsPattern'] = '.*';
$GLOBALS['TYPO3_CONF_VARS']['GFX']['processor'] = 'ImageMagick';
$GLOBALS['TYPO3_CONF_VARS']['GFX']['processor_path'] = '/usr/bin/';
$GLOBALS['TYPO3_CONF_VARS']['GFX']['processor_path_lzw'] = '/usr/bin/';
ADDITIONAL

# v14 doesn't auto-install third-party extensions during `setup`; run the
# explicit extension install. The command is a no-op on v12/v13.
if vendor/bin/typo3 list 2>/dev/null | grep -qE '^\s+extension:setup\b'; then
    vendor/bin/typo3 extension:setup
fi

echo "[bootstrap] done"
