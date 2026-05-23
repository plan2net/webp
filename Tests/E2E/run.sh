#!/usr/bin/env bash
#
# E2E runner for plan2net/webp. Runs against a TYPO3 instance built by
# bootstrap.sh — boots it if missing.
#
# Steps:
#   1. Bootstrap TYPO3 if not cached
#   2. Drop fixture image, apply multi-format extension config + sys_template
#   3. Boot PHP's built-in webserver and curl the root page
#      → IMG_RESOURCE renders → FAL processing fires → AfterFileProcessing
#        listener creates .webp/.avif/.jxl siblings next to the ProcessedFile
#   4. Assert each enabled format's sibling exists on disk
#   5. For nginx then Apache: start with our recipe, curl with four
#      Accept headers against the processed-file URL, assert Content-Type, stop
#
set -euo pipefail

TYPO3_VERSION="${TYPO3_VERSION:-^14.3}"
INSTANCE_DIR="${INSTANCE_DIR:-/tmp/plan2net-webp-e2e/instance}"
PORT="${PORT:-8090}"
PHP_FE_PORT="${PHP_FE_PORT:-8091}"

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
EXTENSION_DIR="${EXTENSION_DIR:-$(cd "$script_dir/../.." && pwd)}"

mkdir -p "$(dirname "$INSTANCE_DIR")" /tmp/plan2net-webp-e2e

# 1. Bootstrap if not already built
if [[ ! -x "$INSTANCE_DIR/vendor/bin/typo3" ]]; then
    bash "$script_dir/bootstrap.sh"
fi

# The cache-apt-pkgs-action drops .deb files in place but doesn't fully
# register them with dpkg, so dpkg-reconfigure is a no-op. TYPO3's FAL
# processing calls "convert"/"identify" by name → symlink them ourselves.
if [[ ! -x /usr/bin/convert ]] && [[ -x /usr/bin/convert-im6.q16 ]]; then
    sudo ln -sf /usr/bin/convert-im6.q16 /usr/bin/convert
fi
if [[ ! -x /usr/bin/identify ]] && [[ -x /usr/bin/identify-im6.q16 ]]; then
    sudo ln -sf /usr/bin/identify-im6.q16 /usr/bin/identify
fi

PUBLIC_DIR="$INSTANCE_DIR/public"
FILEADMIN="$PUBLIC_DIR/fileadmin"
mkdir -p "$FILEADMIN"

# 2. Fixture image + extension config + sys_template
FIXTURE="$FILEADMIN/photo.jpg" php <<'PHP'
<?php
$im = imagecreatetruecolor(1200, 800);
for ($y = 0; $y < 800; $y++) {
    $r = (int) ($y * 255 / 800);
    $color = imagecolorallocate($im, $r, 0, 255 - $r);
    imagefilledrectangle($im, 0, $y, 1199, $y, $color);
}
imagejpeg($im, getenv('FIXTURE'), 92);
PHP

cp "$script_dir/fixtures/settings.php" /tmp/plan2net-webp-e2e/webp-ext-config.php
INSTANCE_DIR="$INSTANCE_DIR" php <<'PHP'
<?php
$instance = getenv('INSTANCE_DIR') ?: '/tmp/plan2net-webp-e2e/instance';
$settingsFile = $instance . '/config/system/settings.php';
$existing = is_file($settingsFile) ? require $settingsFile : [];
$overlay = require '/tmp/plan2net-webp-e2e/webp-ext-config.php';
$merged = array_replace_recursive($existing, $overlay);
file_put_contents($settingsFile, "<?php\n\nreturn " . var_export($merged, true) . ";\n");
PHP

DB_FILE="$(find "$INSTANCE_DIR/var" -name '*.sqlite' 2>/dev/null | head -1)"
if [[ -z "$DB_FILE" ]]; then
    echo "FAIL: sqlite db not found under $INSTANCE_DIR/var" >&2
    exit 1
fi
sqlite3 "$DB_FILE" < "$script_dir/fixtures/content.sql"

# Make sure fileadmin/_processed_ starts empty so we can tell what THIS run
# produced.
rm -rf "$FILEADMIN/_processed_"

"$INSTANCE_DIR/vendor/bin/typo3" cache:flush >/dev/null

# 3. Boot PHP's built-in server and drive the FE
PHP_FE_LOG=/tmp/plan2net-webp-e2e/php-fe.log
php -S "127.0.0.1:${PHP_FE_PORT}" -t "$PUBLIC_DIR" "$PUBLIC_DIR/index.php" \
    >"$PHP_FE_LOG" 2>&1 &
PHP_FE_PID=$!

cleanup_fe() {
    if [[ -n "${PHP_FE_PID:-}" ]] && kill -0 "$PHP_FE_PID" 2>/dev/null; then
        kill "$PHP_FE_PID" 2>/dev/null || true
        wait "$PHP_FE_PID" 2>/dev/null || true
    fi
    PHP_FE_PID=
}
trap cleanup_fe EXIT

# The site config from `typo3 setup --create-site=http://localhost/` matches
# requests on Host: localhost; curl directly to 127.0.0.1 would 404. Pass the
# header so the site router finds the root page.
FE_CURL_OPTS=(-sS -H 'Host: localhost')

# Give php -S a moment to listen (any HTTP response counts — even 404 means
# the listener is up). Silence the first few "Connection refused" failures.
for _ in 1 2 3 4 5 6 7 8 9 10; do
    if curl -s -H 'Host: localhost' -o /dev/null \
        "http://127.0.0.1:${PHP_FE_PORT}/" 2>/dev/null; then
        break
    fi
    sleep 0.5
done

FE_BODY_FILE=/tmp/plan2net-webp-e2e/fe-body.txt
FE_HTTP_CODE=$(curl "${FE_CURL_OPTS[@]}" -o "$FE_BODY_FILE" -w '%{http_code}' "http://127.0.0.1:${PHP_FE_PORT}/" || echo "000")
echo "FE response: HTTP $FE_HTTP_CODE"

if [[ "$FE_HTTP_CODE" != "200" ]]; then
    echo "FAIL: FE returned $FE_HTTP_CODE — body:" >&2
    head -120 "$FE_BODY_FILE" >&2 || true
    echo "--- php -S log ---" >&2
    tail -60 "$PHP_FE_LOG" >&2 || true
    exit 1
fi

# IMG_RESOURCE outputs the processed file path. Pull it out of the body.
PROCESSED_RELPATH=$(grep -oE 'fileadmin/_processed_/[^[:space:]<>"]+\.jpg' "$FE_BODY_FILE" | head -1 || true)
if [[ -z "$PROCESSED_RELPATH" ]]; then
    echo "FAIL: could not locate processed file path in FE response. Body:" >&2
    head -120 "$FE_BODY_FILE" >&2
    echo "--- /usr/bin/convert ---" >&2
    ls -la /usr/bin/convert /usr/bin/convert-* /usr/bin/magick 2>&1 >&2 || true
    /usr/bin/convert -version 2>&1 | head -3 >&2 || true
    echo "--- GFX section of settings.php ---" >&2
    grep -A 3 "'GFX'" "$INSTANCE_DIR/config/system/settings.php" >&2 || true
    echo "--- additional.php ---" >&2
    cat "$INSTANCE_DIR/config/system/additional.php" >&2 || true
    echo "--- sys_template config ---" >&2
    sqlite3 "$DB_FILE" "SELECT config FROM sys_template WHERE uid=1;" >&2 || true
    echo "--- _processed_ dir ---" >&2
    find "$FILEADMIN/_processed_" -type f 2>&1 >&2 || true
    echo "--- TYPO3 logs ---" >&2
    find "$INSTANCE_DIR/var/log" -name '*.log' -exec tail -40 {} + 2>&1 >&2 || true
    exit 1
fi
PROCESSED_ABS="$PUBLIC_DIR/$PROCESSED_RELPATH"
echo "Processed file: /$PROCESSED_RELPATH ($(wc -c <"$PROCESSED_ABS") bytes)"

cleanup_fe
trap - EXIT

# 4. Assert siblings exist next to the ProcessedFile
declare -A SIBLING_PRESENT=()
fail_count=0
pass_count=0
skip_count=0

assert_sibling() {
    local label="$1"
    local suffix="$2"
    local path="${PROCESSED_ABS}${suffix}"
    if [[ -f "$path" ]]; then
        echo "  ✓ $label sibling exists ($(wc -c <"$path") bytes)"
        SIBLING_PRESENT["$label"]=1
        pass_count=$((pass_count + 1))
    else
        echo "  ✗ $label sibling missing at $path"
        SIBLING_PRESENT["$label"]=0
        fail_count=$((fail_count + 1))
    fi
}

echo
echo "== Disk: FE-generated siblings =="
assert_sibling "webp" ".webp"
assert_sibling "avif" ".avif"
assert_sibling "jxl"  ".jxl"

# 5. Webserver content negotiation against the processed-file URL.
render_server_conf() {
    local template="$1"
    local out="$2"
    local modules_dir="${APACHE_MODULES_DIR:-/usr/lib/apache2/modules}"
    local apache_user="${APACHE_USER:-www-data}"
    local apache_group="${APACHE_GROUP:-www-data}"
    sed -e "s#__INSTANCE_PUBLIC__#${PUBLIC_DIR}#g" \
        -e "s#__PORT__#${PORT}#g" \
        -e "s#__APACHE_MODULES__#${modules_dir}#g" \
        -e "s#__APACHE_USER__#${apache_user}#g" \
        -e "s#__APACHE_GROUP__#${apache_group}#g" \
        "$template" > "$out"
}

probe() {
    local accept="$1"
    local expected="$2"
    local got
    got="$(curl -sk -o /dev/null -w '%{http_code} %{content_type}' \
        -H "Accept: $accept" \
        "http://127.0.0.1:${PORT}/${PROCESSED_RELPATH}")"
    local code=${got%% *}
    local ctype=${got#* }
    ctype=${ctype%%;*}  # strip charset
    ctype=${ctype// /}
    if [[ "$code" != "200" ]]; then
        echo "  ✗ Accept '$accept' → HTTP $code (expected 200 / $expected)"
        fail_count=$((fail_count + 1))
    elif [[ "$ctype" == "$expected" ]]; then
        echo "  ✓ Accept '$accept' → $ctype"
        pass_count=$((pass_count + 1))
    else
        echo "  ✗ Accept '$accept' → $ctype (expected $expected)"
        fail_count=$((fail_count + 1))
    fi
}

top_format_for() {
    local accept="$1"
    if [[ "$accept" == *"image/avif"* && "${SIBLING_PRESENT[avif]:-0}" == "1" ]]; then
        echo "image/avif"
    elif [[ "$accept" == *"image/webp"* && "${SIBLING_PRESENT[webp]:-0}" == "1" ]]; then
        echo "image/webp"
    elif [[ "$accept" == *"image/jxl"* && "${SIBLING_PRESENT[jxl]:-0}" == "1" ]]; then
        echo "image/jxl"
    else
        echo "image/jpeg"
    fi
}

probe_all_accept_variants() {
    local server="$1"
    echo
    echo "== ${server}: Accept-header content negotiation =="
    probe "image/avif,image/webp,image/jxl,image/*,*/*" "$(top_format_for 'image/avif,image/webp,image/jxl')"
    probe "image/jxl,image/*,*/*"                       "$(top_format_for 'image/jxl')"
    probe "image/webp,image/*,*/*"                      "$(top_format_for 'image/webp')"
    probe "*/*"                                          "image/jpeg"
}

start_nginx() {
    render_server_conf "$script_dir/nginx.conf" /tmp/plan2net-webp-e2e/nginx.conf
    nginx -c /tmp/plan2net-webp-e2e/nginx.conf &
    NGINX_PID=$!
    sleep 1
}
stop_nginx() {
    if [[ -n "${NGINX_PID:-}" ]] && kill -0 "$NGINX_PID" 2>/dev/null; then
        kill "$NGINX_PID" 2>/dev/null || true
        wait "$NGINX_PID" 2>/dev/null || true
    fi
    NGINX_PID=
}

start_apache() {
    render_server_conf "$script_dir/apache.conf" /tmp/plan2net-webp-e2e/apache.conf
    # /etc/apache2/envvars uses unset vars internally, so it can't be sourced
    # under `set -u`. Define the minimum apache2 needs to start.
    export APACHE_CONFDIR="${APACHE_CONFDIR:-/etc/apache2}"
    export APACHE_RUN_DIR="${APACHE_RUN_DIR:-/var/run/apache2}"
    export APACHE_LOCK_DIR="${APACHE_LOCK_DIR:-/var/lock/apache2}"
    export APACHE_LOG_DIR="${APACHE_LOG_DIR:-/var/log/apache2}"
    export APACHE_PID_FILE="${APACHE_PID_FILE:-/tmp/plan2net-webp-e2e/apache.pid}"
    export APACHE_RUN_USER="${APACHE_RUN_USER:-www-data}"
    export APACHE_RUN_GROUP="${APACHE_RUN_GROUP:-www-data}"
    mkdir -p "$APACHE_RUN_DIR" "$APACHE_LOCK_DIR" "$APACHE_LOG_DIR"
    apache2 -f /tmp/plan2net-webp-e2e/apache.conf -DFOREGROUND &
    APACHE_PID=$!
    sleep 1
}
stop_apache() {
    if [[ -n "${APACHE_PID:-}" ]] && kill -0 "$APACHE_PID" 2>/dev/null; then
        kill "$APACHE_PID" 2>/dev/null || true
        wait "$APACHE_PID" 2>/dev/null || true
    fi
    APACHE_PID=
}

cleanup() {
    stop_nginx
    stop_apache
}
trap cleanup EXIT

if command -v nginx >/dev/null 2>&1; then
    start_nginx
    probe_all_accept_variants "nginx"
    stop_nginx
else
    echo
    echo "== nginx: skipped — daemon not installed =="
    skip_count=$((skip_count + 1))
fi

if command -v apache2 >/dev/null 2>&1; then
    start_apache
    probe_all_accept_variants "apache"
    stop_apache
else
    echo
    echo "== apache: skipped — apache2 not installed =="
    skip_count=$((skip_count + 1))
fi

# Summary
echo
echo "================================="
echo " Pass: $pass_count   Fail: $fail_count   Skip: $skip_count"
echo "================================="
if [[ $fail_count -gt 0 ]]; then
    echo "FAILED."
    if [[ -f /tmp/plan2net-webp-e2e/nginx-error.log ]]; then
        echo "--- nginx-error.log ---"
        tail -30 /tmp/plan2net-webp-e2e/nginx-error.log
    fi
    if [[ -f /tmp/plan2net-webp-e2e/apache-error.log ]]; then
        echo "--- apache-error.log ---"
        tail -30 /tmp/plan2net-webp-e2e/apache-error.log
    fi
    exit 1
fi
echo "OK."
