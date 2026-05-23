# WebP for TYPO3 CMS

[![Packagist Version](https://img.shields.io/packagist/v/plan2net/webp.svg)](https://packagist.org/packages/plan2net/webp)
[![Downloads](https://img.shields.io/packagist/dt/plan2net/webp.svg)](https://packagist.org/packages/plan2net/webp)
[![Tests](https://github.com/plan2net/webp/actions/workflows/tests.yml/badge.svg)](https://github.com/plan2net/webp/actions/workflows/tests.yml)
[![Supported TYPO3](https://img.shields.io/badge/TYPO3-12.4%20%7C%2013.4%20%7C%2014-orange.svg)](https://get.typo3.org/)
[![Supported PHP](https://img.shields.io/packagist/php-v/plan2net/webp.svg)](https://packagist.org/packages/plan2net/webp)
[![License](https://img.shields.io/badge/license-GPL--3.0-blue.svg)](LICENSE)

Serve WebP to browsers that support it, **without changing your URLs or HTML**. The extension creates sibling files (`photo.jpg.webp`) next to every processed image; your webserver swaps them in transparently via `Accept`-header content negotiation. Browsers that don't support WebP receive the original format.

See [CHANGELOG.md](CHANGELOG.md) for release notes.

## When to use this vs. TYPO3 14 native WebP

TYPO3 v14 introduced native WebP support via `$GLOBALS['TYPO3_CONF_VARS']['GFX']['imageFileConversionFormats']`. The core mechanism converts processed image **output** to WebP: the processed file's extension is `.webp` and the URL changes accordingly (`photo.jpg` → `photo.webp`).

This extension solves a different problem:

| Concern                              | This extension                           | TYPO3 v14 native              |
|--------------------------------------|------------------------------------------|-------------------------------|
| URL of served image                  | Unchanged (`photo.jpg`)                  | Changed (`photo.webp`)        |
| HTML / templates                     | Unchanged                                | Reference new URL             |
| Fallback for non-WebP browsers       | Transparent via webserver                | Need `<picture>` or polyfill  |
| Requires webserver rewrite rule      | Yes                                      | No                            |
| Works with cached HTML / CDN URLs    | Yes                                      | Cache invalidation needed     |

Use the core mechanism when you can change URLs. Use this extension when you can't.

## What is WebP?

> WebP is a modern image format that provides superior lossless and lossy compression for images on the web. WebP lossless images are 26% smaller in size compared to PNGs. WebP lossy images are 25–34% smaller than comparable JPEG images at equivalent SSIM quality index.
>
> — [developers.google.com/speed/webp](https://developers.google.com/speed/webp/)

As of May 2026, WebP is supported by **~95.6%** of users globally ([caniuse.com/webp](https://caniuse.com/webp), per StatCounter data updated April 2026). The content-negotiation approach in this extension means even browsers without WebP support keep working — they just receive the original JPEG/PNG/GIF.

WebP was released by Google in 2010. It supports both lossy and lossless compression, transparency (an alpha channel at far lower overhead than PNG's), animation, and ICC color profiles. Smaller image payloads improve Core Web Vitals directly — Largest Contentful Paint (LCP) especially benefits when image bytes are reduced without changing rendered dimensions, and a 25–34% bandwidth reduction adds up on image-heavy pages and mobile connections.

## Compatibility

| Extension version | TYPO3              | PHP            | Status   |
|-------------------|--------------------|----------------|----------|
| 14.x              | 12.4, 13.4, 14.x   | 8.2, 8.3, 8.4  | Current  |
| 13.x              | 13.4               | 8.2, 8.3       | Legacy   |
| 5.x               | 12.4               | 8.1, 8.2       | Legacy   |

## Requirements

A WebP-capable image converter. The extension supports four:

- **ImageMagick** or **GraphicsMagick** — TYPO3's built-in graphics processor, compiled with WebP support.
- **PHP GD** — needs the `IMG_WEBP` flag at runtime.
- **libvips** — in-process via [`jcupitt/vips`](https://packagist.org/packages/jcupitt/vips) + PHP `ext-ffi`, or as the `vips` CLI binary through the ExternalConverter.
- Any other external WebP encoder such as [`cwebp`](https://developers.google.com/speed/webp/docs/cwebp).

Verify your existing setup:

```sh
# GraphicsMagick — should return "yes"
gm version | grep WebP

# ImageMagick — should include "webp" in the list
convert -version | grep -i webp
```

If neither has WebP support, install `cwebp` and point the extension at it via the [`parameters`](#parameters) configuration.

## Installation

```sh
composer require plan2net/webp
```

Then:

1. Activate the extension if your TYPO3 is in non-Composer mode (Composer mode activates it automatically).
2. Flush TYPO3 and PHP caches.
3. Clear processed files (*System → Maintenance → Remove Temporary Assets* on TYPO3 v14; *Admin Tools → Maintenance* on v12/v13).
4. Add the [webserver rewrite rules](#webserver-configuration).

## Updating

After a `composer update`, **save the extension settings at least once** via the Extension Configuration backend module (*System → Settings → Extension Configuration → webp* on TYPO3 v14; *Admin Tools → Settings → Extension Configuration → webp* on v12/v13). TYPO3 only writes default values to `LocalConfiguration` when you save the settings form, so any new defaults the upgraded version ships won't take effect until you do.

## Configuration

![Extension settings](Documentation/extension_settings.png)

| Setting                                       | Default                                     | Purpose                                                |
|-----------------------------------------------|---------------------------------------------|--------------------------------------------------------|
| [`parameters`](#parameters)                   | See below                                   | Per-mime-type converter parameters                     |
| [`mime_types`](#mime_types)                   | `image/jpeg,image/png,image/gif`            | Source mime types to convert                           |
| [`convert_all`](#convert_all)                 | `1`                                         | Convert all images, not just `_processed_`             |
| [`silent`](#silent)                           | `1`                                         | Suppress converter stdout/stderr (Linux only)          |
| [`hide_webp`](#hide_webp)                     | `1`                                         | Hide `.webp` files in the BE file list                 |
| [`filter_pattern`](#filter_pattern)           | `/\.(jpe?g\|png\|gif)\.webp$/i`             | PCRE for which `.webp` to hide (only when `hide_webp`) |
| [`exclude_directories`](#exclude_directories) | _(empty)_                                   | Skip processing for matching paths                     |
| [`use_system_settings`](#use_system_settings) | `1`                                         | Reuse GFX color profile settings (MagickConverter)     |
| [`async`](#async)                             | `0`                                         | Queue conversions for a scheduler worker instead of running them on the page-render path |
| [`async_throttle_ms`](#async_throttle_ms)     | `0`                                         | Random per-conversion sleep (ms) in the worker; 0 disables                |

### `parameters`

Each entry is `mime/type::params`, separated by `|`. The `::` is significant — a single colon does not match the parser and silently falls through to the fallback branch.

Default (ImageMagick/GraphicsMagick):

```
parameters = image/jpeg::-quality 85 -define webp:lossless=false|image/png::-quality 75 -define webp:lossless=true|image/gif::-quality 85 -define webp:lossless=true
```

Per-mime-type breakdown:

| Mime type    | Default parameters                          |
|--------------|---------------------------------------------|
| `image/jpeg` | `-quality 85 -define webp:lossless=false`   |
| `image/png`  | `-quality 75 -define webp:lossless=true`    |
| `image/gif`  | `-quality 85 -define webp:lossless=true`    |

Reference:

- [ImageMagick WebP options](https://www.imagemagick.org/script/webp.php)
- [GraphicsMagick options](http://www.graphicsmagick.org/GraphicsMagick.html)

> [!WARNING]
> Try raising `quality` before reaching for `webp:lossless=true`; lossless can produce files *larger* than the original.

#### Using an external binary

Supply a command string with exactly two `%s` placeholders for the source and target file:

```
image/jpeg::/usr/bin/cwebp -jpeg_like %s -o %s|image/png::/usr/bin/cwebp -lossless %s -o %s|image/gif::/usr/bin/gif2webp %s -o %s
```

See [`cwebp` documentation](https://developers.google.com/speed/webp/docs/cwebp).

With libvips's `vips` CLI binary:

```
image/jpeg::/usr/bin/vips webpsave %s %s --Q 85 --effort 4 --smart-subsample|image/png::/usr/bin/vips webpsave %s %s --Q 75 --lossless --effort 4|image/gif::/usr/bin/vips webpsave %s[n=-1] %s --Q 75 --lossless --effort 4 --mixed
```

The GIF entry uses `%s[n=-1]` on the source so libvips reads all frames; without it only the first frame survives and `--mixed` (a save-side option) can't recover the rest. See the [libvips CLI reference](https://www.libvips.org/API/current/using-the-cli.html).

#### Using libvips natively

Pick **libvips (native)** from the converter dropdown for the fastest path: 2–3× faster than MagickConverter at equivalent quality, substantially less memory, and animated GIFs survive as animated WebP automatically.

Install the libvips shared library, enable PHP's FFI extension, and require the PHP binding:

```sh
apt install libvips-tools           # Debian/Ubuntu — pulls in libvips42 / libvips42t64
brew install vips                   # macOS
composer require jcupitt/vips
```

Set `parameters` to:

```
image/jpeg::Q=85 smart_subsample=true effort=4|image/png::Q=75 lossless=true effort=4|image/gif::Q=75 lossless=true mixed=true effort=4
```

Options are passed straight to libvips's `webpsave` — see the [option reference](https://www.libvips.org/API/current/VipsForeignSave.html#vips-webpsave) for the full list.

> [!IMPORTANT]
> Set `ffi.enable=true` in php.ini (not `preload` — jcupitt/vips does not support FFI preloading). On PHP 8.3+ also set `zend.max_allowed_stack_size=-1`; without it the default stack limit can cause spurious conversion failures.

### `mime_types`

```
# cat=basic; type=string; label=Supported mime types (comma separated)
mime_types = image/jpeg,image/png,image/gif
```

Only source files whose mime type is in this comma-separated list are considered for conversion.

### `convert_all`

```
# cat=basic; type=boolean; label=Convert all images in local and writable storage and save a copy in Webp format; disable to convert images in the _processed_ folder only
convert_all = 1
```

When enabled (default), every image in every local + writable storage is saved as a `.webp` sibling — not just images that TYPO3 has actually processed into `_processed_/`. To revert to processing-only behaviour, disable the checkbox.

Source-folder siblings are kept in sync with TYPO3's FAL operations: moving an image moves its `.webp`, deleting removes it, replacing drops the stale `.webp` so the next render produces a fresh one. When a storage has a recycler, the `.webp` follows the file into the recycler so restore keeps the pair intact. No configuration needed.

### `silent`

```
# cat=basic; type=boolean; label=Suppress output (stdout, stderr) from the external converter command
silent = 1
```

Suppress stdout/stderr from external converters. Linux only.

### `hide_webp`

```
# cat=basic; type=boolean; label=Hide .webp files in backend file list module
hide_webp = 1
```

Hides `.webp` files in the backend file list module. The pattern controlling **which** `.webp` files are hidden is [`filter_pattern`](#filter_pattern).

For a more customised behaviour (e.g. show siblings only to a specific BE group), override `$GLOBALS['TYPO3_CONF_VARS']['SYS']['fal']['defaultFilterCallbacks']` in your own extension — see `ext_localconf.php` for the registration this extension performs.

### `filter_pattern`

```
# cat=basic; type=string; label=Pattern to filter out files
filter_pattern = /\.(jpe?g|png|gif)\.webp$/i
```

PCRE pattern matched against the file identifier when [`hide_webp`](#hide_webp) is enabled. The default matches the sibling-file naming this extension produces (e.g. `photo.jpg.webp`) without hiding standalone `.webp` files. Override if you use a custom naming scheme.

Invalid patterns are silently ignored (no files are hidden, no errors raised).

### `exclude_directories`

```
# cat=basic; type=string; label=Exclude processing of images from specific directories (separated by semicolon)
exclude_directories =
```

Skip processing for images under any of the listed paths (semicolon-separated).

Example: `/fileadmin/demo/special;/another-storage/demo/exclusive`

### `use_system_settings`

```
# cat=basic; type=boolean; label=Use the system GFX "processor_stripColorProfileCommand"/"processor_stripColorProfileParameters" setting for the MagickConverter converter
use_system_settings = 1
```

Applies only to `MagickConverter`. When enabled, the value of `$GLOBALS['TYPO3_CONF_VARS']['GFX']['processor_stripColorProfileCommand']` and `processor_stripColorProfileParameters` is appended to converter arguments automatically — no need to repeat the setting per mime type.

`PhpGdConverter` and external-binary configurations ignore this flag.

### `async`

```
# cat=async; type=boolean; label=Enable asynchronous conversion
async = 0
```

When enabled, the `AfterFileProcessing` listener writes a row to `tx_webp_queue` instead of running the converter inside the request. Conversions then happen out-of-band via the `webp:process-queue` CLI command, typically registered as a TYPO3 Scheduler task. See [Async mode](#async-mode) below for setup.

When disabled (default), conversions run synchronously exactly as before.

### `async_throttle_ms`

```
# cat=async; type=int+; label=Random sleep (ms) between conversions
async_throttle_ms = 0
```

Pause for a random interval between conversions inside the worker. Value `0` means no pause. Value `N > 0` means each pause is `random(N/2, N*3/2)` milliseconds — modeled on `wget --random-wait` to avoid lock-step bursts. Useful on tight-CPU servers when a batch of conversions would otherwise saturate the box. Applies to both queue mode and `--folder` mode.

## Async mode

By default the extension converts images synchronously inside the request that processes the source file. On image-heavy pages or large-fileadmin sites this adds latency to every render. Enabling `async = 1` moves the conversion off the render path:

1. Set `async = 1` in the extension configuration.
2. Run TYPO3's database analyzer so `tx_webp_queue` is created.
3. Register a TYPO3 Scheduler task: **System → Scheduler → Add task → Type: "Process conversion queue (webp)"**. Pick a frequency that matches your throughput (every minute for busy sites, hourly for low-traffic).
4. Make sure the scheduler itself runs — either via `vendor/bin/typo3 scheduler:run` in cron, or a daemonized runner.

The listener will now enqueue new conversions; the scheduler task drains the queue in the background. Existing siblings stay; the extension does not retroactively backfill.

### Sweeping non-FAL folders

Some image folders (notably `typo3temp/assets/online_media/` for YouTube/Vimeo preview thumbnails) live outside TYPO3's File Abstraction Layer, so the listener never sees them. The `--folder` argument bypasses the queue and converts files directly:

```sh
vendor/bin/typo3 webp:process-queue --folder=typo3temp/assets/online_media/
```

Register this as a second Scheduler task ("Execute console command") if you want it to run periodically. Paths resolve relative to the public web root and are restricted to it for safety.

## Webserver configuration

The webserver inspects the client's `Accept` header and rewrites the request to the `.webp` sibling when both are true:

- the client advertised `image/webp` support
- the sibling exists on disk

Below are examples for nginx and Apache. **Adapt them to your stack** — these aren't drop-in copies.

> [!IMPORTANT]
> Make sure no earlier rule in your config short-circuits the request for the image extensions you target (e.g. a generic static-asset block). If so, move the WebP rules above it or rework the earlier rule.

### nginx

Add a `map` directive in the `http` block:

```nginx
map $http_accept $webp_suffix {
    default   "";
    "~*webp"  ".webp";
}
```

If you front the site with Cloudflare, prefer this variant — Cloudflare caches per response and would otherwise mix WebP and non-WebP variants:

```nginx
map $http_accept $webpok {
    default   0;
    "~*webp"  1;
}
map $http_cf_cache_status $iscf {
    default   1;
    ""        0;
}
map $webpok$iscf $webp_suffix {
    11  "";
    10  ".webp";
    01  "";
    00  "";
}
```

Add the location block to your `server`:

```nginx
location ~* ^.+\.(png|gif|jpe?g)$ {
    add_header Vary "Accept";
    add_header Cache-Control "public, no-transform";
    try_files $uri$webp_suffix $uri =404;
}
```

#### Restrict by user agent (optional)

```nginx
location ~* ^.+\.(png|gif|jpe?g)$ {
    if ($http_user_agent !~* (Chrome|Firefox|Edge)) {
        set $webp_suffix "";
    }
    …
}
```

### Apache

The first two directives are already part of TYPO3's default `.htaccess` template (`typo3/sysext/install/Resources/Private/FolderStructureTemplateFiles/root-htaccess`); they're shown here for completeness. We assume `mod_rewrite.c` is enabled.

```apache
RewriteEngine On
AddType image/webp .webp

RewriteCond %{HTTP_ACCEPT} image/webp
RewriteCond %{REQUEST_FILENAME} (.*)\.(?i:png|gif|jpe?g)$
RewriteCond %{REQUEST_FILENAME}\.webp -f
RewriteRule ^ %{REQUEST_FILENAME}\.webp [L,T=image/webp]

<IfModule mod_headers.c>
    <FilesMatch "\.(png|gif|jpe?g)$">
        Header append Vary Accept
    </FilesMatch>
</IfModule>
```

#### When `%{REQUEST_FILENAME}` doesn't resolve

Some environments — shared hosting (IONOS etc.), Windows Apache, or setups where the rewrite runs before path resolution — return 403/404 with the `%{REQUEST_FILENAME}` form because Apache can't map the request to a filesystem path at that stage. Two portable alternatives:

```apache
RewriteRule ^ %{REQUEST_URI}.webp [L,T=image/webp]
```

or:

```apache
RewriteRule ^(.*)$ $1.webp [L,T=image/webp]
```

Substitute either for the `RewriteRule ^ %{REQUEST_FILENAME}\.webp …` line above.

#### Restrict by user agent (optional)

```apache
RewriteCond %{HTTP_ACCEPT} image/webp
RewriteCond %{HTTP_USER_AGENT} ^.*(Chrome|Firefox|Edge).*$ [NC]
…
```

## Remote storages (S3, Azure, custom FAL drivers)

![Generate WebP variants storage setting](Documentation/generate_webp_variants.png)

Out of the box every Local writable storage produces `.webp` siblings — that's
the default mode for the new *Generate WebP variants* field on each storage
record. To opt in a non-Local storage (S3 mount, Azure mount, any custom FAL
driver), edit the storage record and set the field to **Enabled**.

| Storage record field | Value | Result |
|---|---|---|
| *Generate WebP variants* | **Auto** *(default)* | On for `driver = Local`, off for everything else. Identical to pre-14.2 behaviour. |
| *Generate WebP variants* | **Enabled**          | On regardless of driver type. Use to opt a remote storage in. |
| *Generate WebP variants* | **Disabled**         | Off regardless of driver type. Use to take a Local storage out of the pipeline temporarily. |

Once enabled, behaviour is identical to a Local storage: the `.webp` lands at
`<original>.webp` on the storage, and the four FAL lifecycle events (move,
replace, delete, recycler) keep siblings in sync.

> [!IMPORTANT]
> Enable [`async = 1`](#async) for any storage with a non-Local driver.
> Synchronous mode adds the driver's upload latency to every page render that
> processes an image (typical S3 PUT: 100–500 ms, more on cold connections).
> The async queue moves that work off the render path.

### Serving the right format on a CDN

This extension *writes* the sibling on the storage. Serving the right format
per request — the `Accept`-header rewrite — is the edge's job. Sketches:

- **CloudFront**: attach a CloudFront Function on viewer request that
  inspects `Accept`, and when `image/webp` is present rewrites the URI from
  `/path/photo.jpg` to `/path/photo.jpg.webp`. Pair with a viewer-response
  function that sets `Vary: Accept`.
- **Cloudflare**: a Worker doing the same rewrite, or an Image Resizing
  policy if the account has it.
- **Direct S3 origins without an edge function**: not supported by S3 itself;
  you need a layer in front (CloudFront/equivalent or an origin proxy).

The webserver-rewrite recipes above still apply when TYPO3's origin sits in
front of the storage (e.g., S3 mounted only for backend uploads, but served
through the TYPO3 instance).

## Verifying it works

Two things to check: that WebP files are actually generated, and that the webserver serves them when the client supports it.

### WebP file generation

Browse to `fileadmin/_processed_` and look for `.webp` siblings:

```
csm_foo-bar_4f3d6bb7d0.jpg
csm_foo-bar_4f3d6bb7d0.jpg.webp
```

With `convert_all = 1`, you'll also find `.webp` siblings next to originals in `fileadmin/` itself.

### Delivery

Request a JPEG/PNG with an `Accept: image/webp` header:

```sh
curl -H "Accept: image/webp" -I https://example.tld/fileadmin/_processed_/b/2/csm_foo-bar_4f3d6bb7d0.jpg
# expect: Content-Type: image/webp
```

Or open the URL in a browser and check the response headers in the developer tools — despite the `.jpg` suffix the `Content-Type` should be `image/webp`:

![Response headers showing image/webp](Documentation/headers.png)

## Diagnosing your installation

The `webp:diagnose` CLI command walks the full WebP delivery chain end-to-end and points at the first failing link.

```bash
vendor/bin/typo3 webp:diagnose                              # health check
vendor/bin/typo3 webp:diagnose --url=https://example.com    # also probe webserver delivery
vendor/bin/typo3 webp:diagnose --file=42                    # also investigate one file
```

It reports:

- Storages: mode, driver, sibling count, plus phantom rows with unregistered drivers.
- Converter: class, binary availability, parameter parsing.
- Async pipeline: queue size, age, scheduler task state.
- Failed-conversion cache: total, recent rows, dominant config hash.
- Delivery probe (`--url=…`): two `Accept` HEADs + `Vary: Accept`.
- File deep dive (`--file=<uid>`): metadata + both sibling tables + failed-attempts rows.

**Honest limits:** the probe runs from this machine. CDN behaviour at the edge can differ from what we observe locally. Run the probe from a host inside your CDN's pull zone for the most accurate read.

Useful flags:

| Flag | Purpose |
|---|---|
| `--insecure` | Disable TLS certificate verification on the HTTP probe — for self-signed or otherwise untrusted certs |
| `--probe-timeout=<sec>` | HTTP probe timeout (default: 10) |

## Troubleshooting

Every conversion problem is logged to TYPO3's log (`var/log/typo3_*.log` by default). Start there.

Common cases:

| Symptom                                       | Likely cause                                                                 |
|-----------------------------------------------|------------------------------------------------------------------------------|
| No `.webp` files appear in `_processed_/`     | Converter binary lacks WebP support, or `mime_types` excludes the source     |
| Files are bigger than the original            | Automatically removed and **not retried** with the same configuration        |
| WebP renders darker / off-colour              | `$GLOBALS['TYPO3_CONF_VARS']['GFX']['processor_colorspace']` (e.g. `sRGB`)   |
| Apache rewrite returns 403/404                | [`%{REQUEST_FILENAME}` doesn't resolve](#when-request_filename-doesnt-resolve) |
| File still served as JPEG after a successful generation | Webserver rewrite rule missing or shadowed by another rule          |
| Sibling left behind after deleting the source | None — that's the bug fixed in 14.0.0; upgrade                              |

After changing `processor_colorspace`, clean up any processed files via the Maintenance backend module (*System → Maintenance → Remove Temporary Assets* on TYPO3 v14; *Admin Tools → Maintenance* on v12/v13) so the change takes effect on existing images.

## Known limitations

- **Animated GIFs.** Only the libvips routes preserve animation: `VipsConverter` does it automatically, and the `vips` CLI route requires `%s[n=-1]` on the GIF source argument (see [`parameters`](#parameters)). `MagickConverter`, `PhpGdConverter`, and `cwebp`-based configurations produce single-frame output.
- **ImageMagick / GraphicsMagick must be compiled with WebP support.** See [Requirements](#requirements).
- **Cross-storage FAL moves are not handled.** If you move a file between two different storages, the sibling at the source storage is left orphaned. Lazy regeneration handles the target storage on next render. Single-storage moves work correctly.
- **`use_system_settings` only applies to `MagickConverter`.** `PhpGdConverter` and external-binary configurations ignore it.

## Maintenance

To remove all generated `.webp` files (e.g. before a converter or quality change):

1. *System → Maintenance → Remove Temporary Assets* (TYPO3 v14) or *Admin Tools → Maintenance → Remove Temporary Assets* (v12/v13).
2. Click *Scan temporary files*.
3. Click the button labelled with the storage path.

The button label only mentions `_processed_/`, but all processed files in the storage are removed.

The next page render will regenerate `.webp` siblings using the current configuration.

## Drawbacks

- **Extra CPU.** Every processed image is reprocessed for the `.webp` sibling. The work happens once per image (results are cached on disk) but adds latency to first-time renders.
- **Extra disk.** WebP siblings are typically 65–75% of the source file size (Google's reference numbers: WebP lossy is 25–34% smaller than JPEG; lossless is 26% smaller than PNG). With `convert_all = 1` enabled this applies to every source image, not just processed variants.

## Alternatives

- **TYPO3 v14 native WebP** — see [the comparison above](#when-to-use-this-vs-typo3-14-native-webp). Best fit when you can change URLs.
- **Apache `mod_pagespeed` / nginx `ngx_pagespeed`** — Google's automatic image-rewriting modules. Equal end-result with `pagespeed EnableFilters convert_jpeg_to_webp;` plus `convert_to_webp_lossless;`, but more involved to set up and operate.
- **Cloudflare Polish** or similar CDN-level image optimisation.

## Development

```sh
composer install
.Build/bin/phpunit -c phpunit.xml                       # unit tests
typo3DatabaseDriver=pdo_sqlite \
    .Build/bin/phpunit -c phpunit-functional.xml        # functional tests
.Build/bin/php-cs-fixer fix --config=php-cs-fixer.config.php --dry-run
```

CI runs the full PHP × TYPO3 matrix on every push and pull request — see [`.github/workflows/tests.yml`](.github/workflows/tests.yml).

## Credits

Inspired by [Angela Dudtkowski](https://www.clickstorm.de/agentur/)'s `cs_webp` extension. Thanks Angela.

Thanks to Xavier Perseguers for the Cloudflare hint and to Marcus Förster for simplifying the Apache rewrite rules.

## License

GPL-3.0-or-later — see [LICENSE](LICENSE).

## Spread some love

Send us a postcard from your favourite place and tell us how much you love TYPO3 and OpenSource:

> plan2net GmbH
> Sieveringerstraße 37
> 1190 Vienna, Austria
