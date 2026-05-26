# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [14.5.1] - 2026-05-26

### Changed

- `parameters_avif` and `parameters_jxl` now ship ImageMagick-compatible defaults; `converter_avif` and `converter_jxl` default to `MagickConverter`. Enabling AVIF or JPEG XL via `formats_enabled` works out of the box on the typical TYPO3 host — no manual parameter string required. Override either to switch to libvips or an external binary.

### Fixed

- `webp:diagnose` parameter-parsing check now distinguishes empty, malformed-global, and missing-per-mime cases and emits the concrete recommended value for the configured converter, instead of pointing at the README.

### Documentation

- README adds dedicated `parameters_avif` and `parameters_jxl` sections with per-backend recipes (ImageMagick, libvips, external binaries).

## [14.5.0] - 2026-05-26

### Added

- **WebP, AVIF, and JPEG XL output in any combination.** A new `formats_enabled` setting (default `webp`) lets you pick which sibling formats this install produces — e.g. `formats_enabled = webp,avif` to ship both, or `webp,avif,jxl` to ship all three. Each non-webp format has its own converter, parameters, and mime-types tab in the Extension Configuration form (`converter_avif`, `parameters_avif`, `mime_types_avif`; same for `jxl`). The existing `converter` / `parameters` / `mime_types` keys remain the source of truth for the WebP slot. The 4-converter × 3-format support matrix (`VipsConverter`, `MagickConverter`, `ExternalConverter`, `PhpGdConverter`) is in the README. Closes [#94](https://github.com/plan2net/webp/issues/94).
- `webp:diagnose --format=<webp|avif|jxl>` to restrict the report to one format. The delivery probe now sends four `Accept` headers (avif/jxl/webp/`*/*`) and verifies the server returns the highest-priority format the install actually generates. Storages, converter health, async pipeline, failed-attempts cache, and per-file deep-dive all report per format.
- `webp:process-queue --folder=<path>` now sweeps for every enabled output format, not just WebP.

### Changed

- Sibling lifecycle (move / rename / replace / delete) now covers all three formats. Any on-disk `.avif` or `.jxl` sibling follows its original alongside the existing `.webp` handling.
- Default `filter_pattern` now matches `.avif` and `.jxl` siblings as well as `.webp`. Custom values are left untouched; admins who pinned a webp-only regex keep their value.

### Fixed

- A previously-good sibling is no longer deleted when a fresh conversion attempt turns out larger than the original. The failed-attempts cache already prevents the next render from retrying with the same parameters; we keep the file the webserver was happily serving.
- Renaming a source file no longer overwrites a same-named file at the destination. The orphaned source sibling is cleaned up and the next render produces a fresh destination sibling, instead of silently replacing whatever was there.
- Enabling a format without its converter or parameters no longer generates an error on every render. The listener skips the unconfigured format with a one-line notice instead.
- `webp:diagnose` degrades gracefully on installs that haven't run the TYPO3 Database Analyzer after upgrade — the affected sections detect the missing `format` column and point the admin at the analyzer instead of throwing.
- `webp:diagnose` no longer certifies PhpGdConverter as AVIF/JPEG XL capable. PhpGd is WebP-only at runtime; the diagnose check now mirrors that.
- `webp:diagnose` Accept-header probe grades against the format actually available on disk, instead of always comparing against the highest-priority enabled format — eliminates the spurious "server prefers webp over avif" warning when a file has no AVIF sibling yet.
- `PhpGdConverter` clamps `quality` values above 100 (PHP `imagewebp` documents 0–100).
- `ext_emconf.php` now declares `php >= 8.2.0`, matching the long-standing composer constraint. TER installs on PHP 8.1 no longer fetch the extension only to fatal on first request.
- Async-mode label in the Extension Configuration backend module no longer breaks mid-sentence — TYPO3's EM UI was splitting the label on the `webp:` colon.
- `composer.json` now `suggest`s `typo3/cms-frontend` for installs that pick the PhpGd backend (PhpGd imports `TYPO3\CMS\Frontend\Imaging\GifBuilder`).

### Upgrade notes

- **Run the TYPO3 Database Analyzer.** `tx_webp_queue` and `tx_webp_failed` gain a `format` column; existing rows remain valid (`format` defaults to `webp`) but the schema must match. Stop the Scheduler before running the analyzer if you're worried about concurrent enqueues during the `ALTER TABLE` — queued work is a transient working set and `TRUNCATE tx_webp_queue` before the analyzer is also fine.
- **Flush all caches after deploy.** The compiled DI container caches references to the renamed service classes; a stale container will fatal on the first request that touches them. `vendor/bin/typo3 cache:flush` or *Install Tool → Maintenance → Flush cache* resolves it.
- **Re-save the Extension Configuration if you customised `filter_pattern`** to the previous webp-only default. The old default `'/\.(jpe?g|png|gif)\.webp$/i'` does not hide `.avif` / `.jxl` siblings. The new default covers all three; admins who pinned a webp-only regex keep their value untouched.

## [14.4.1] - 2026-05-21

### Bug fixes

- `webp:diagnose` no longer requires `symfony/process`, which is not bundled in TYPO3 v12.4 core. Classic-mode (non-Composer) installs on v12 hit a fatal `Class "Symfony\Component\Process\Process" not found` when running the command. The MagickConverter health check now uses PHP's native `proc_open` with the same 5-second timeout behaviour, removing the runtime dependency on `symfony/process` entirely (also dropped from `composer.json` `require`). Closes [#116](https://github.com/plan2net/webp/issues/116).

## [14.4.0] - 2026-05-19

### Added

- libvips as a first-class conversion backend. Pick **libvips (native)** from the converter dropdown to use a new `VipsConverter` that calls libvips in-process via [`jcupitt/vips`](https://packagist.org/packages/jcupitt/vips) (2.x) + PHP `ext-ffi` — typically 2–3× faster than `MagickConverter` at equivalent quality, substantially less memory, and animated GIFs survive as animated WebP automatically (`n=-1` load + `mixed=true` save). The `vips` CLI binary also works through the existing `ExternalConverter` and preserves GIF animation when the GIF entry uses `%s[n=-1]` on the source argument. Parameter format for the native backend is space-separated `key=value` pairs per mime type, passed straight to libvips's `webpsave`. `webp:diagnose` reports libvips availability (ext-ffi state, package, shared library reachable via `Vips\Config::version()`) and warns on PHP 8.3+ if `zend.max_allowed_stack_size=-1` is not set. README and reST documentation updated. CI matrix exercises the native backend on every PHP × TYPO3 cell.

## [14.3.0] - 2026-05-16

### Added

- `webp:diagnose` CLI command. Reports per-storage WebP mode (incl. phantom rows whose driver isn't registered), converter health (PHP GD / ImageMagick / GraphicsMagick / external binary), async pipeline + scheduler state, recent failed conversion attempts, an optional HTTP probe of the webserver's Accept-header rewrite (with `Vary: Accept` check), and an optional per-file deep dive. Single recommendation block points at the first finding; exit code is `1` only on real failures so the command works as a deployment gate. README and reST documentation updated.

## [14.2.0] - 2026-05-15

### Added

- Per-storage opt-in for `.webp` generation on non-Local FAL drivers. A new `tx_webp_mode` field on `sys_file_storage` (Storage record → *Generate WebP variants*) selects **Auto** (default, preserves pre-14.2 behaviour — on for Local, off for everything else), **Enabled** (force on regardless of driver), or **Disabled** (force off). Closes [#108](https://github.com/plan2net/webp/issues/108).
- README and reST documentation for remote storages and the CDN-edge serving recipe (CloudFront Function / Cloudflare Worker).

### Changed

- WebP publish step now routes through FAL APIs (`ResourceStorage::updateProcessedFile()` for processing-folder targets, `Folder::addFile(..., DuplicationBehavior::REPLACE)` for source-folder targets) instead of direct filesystem writes. The driver overwrites atomically on `REPLACE` — a transient upload failure no longer leaves the user without a previously-valid sibling.
- WebP sibling lifecycle (move/rename/replace/delete/recycler) uses FAL public APIs (`ResourceStorage::moveFile`, `renameFile`, `getFile`, `deleteFile`) instead of direct filesystem ops. Works driver-agnostically; remote-driver storages no longer accumulate orphans on cross-storage moves.
- Sibling now follows the original on rename too — previously only inter-folder moves were tracked, so a BE "rename" left the `.webp` stranded at the old filename.

### Fixed

- TYPO3 v12 ships `SYS/mediafile_ext` without `webp`, so the new FAL publish path tripped `ResourceConsistencyService::isFileExtensionAllowed`. `ext_localconf.php` now appends `webp` to that list if absent — no-op on v13/v14 where it's already there.

## [14.1.1] - 2026-05-15

### Changed

- `typo3/cms-install` and `typo3/cms-scheduler` moved from `require` to `suggest`. The UpgradeWizard class only loads when cms-install's attribute-autoconfigure scans services, and the scheduler task wrapper only loads when cms-scheduler actually instantiates it — both are dormant on installs that don't have the packages. The scheduler-task registration in `ext_localconf.php` is now guarded with `class_exists` for explicit clarity. Users running async mode still need cms-scheduler installed; users running the upgrade wizard still need cms-install installed.

## [14.1.0] - 2026-05-15

### Added

- Opt-in **async conversion mode** (`async = 1`): WebP conversions are queued in a new `tx_webp_queue` table and processed out-of-band by a TYPO3 Scheduler task. Closes [#17](https://github.com/plan2net/webp/issues/17).
- New CLI command `webp:process-queue` with optional `--folder=PATH` argument to convert images in non-FAL folders (e.g., `typo3temp/assets/online_media/`). Closes [#73](https://github.com/plan2net/webp/issues/73).
- New `async_throttle_ms` configuration to space conversions out with randomized jitter, preventing thundering-herd CPU/IO.
- UpgradeWizard `webp.truncateFailedAttemptsBeforeColumnResize` to unblock upgrades from older releases that shipped `tx_webp_failed.configuration_hash` as `VARCHAR(40)`; the wizard empties the cache of failed attempts so TYPO3's database analyzer can shrink the column to `VARCHAR(32)`. Closes [#95](https://github.com/plan2net/webp/issues/95).

### Fixed

- `ExternalConverter` now preserves non-ASCII bytes in filenames (e.g., German umlauts, accented characters) when passing paths to external converters like `cwebp`. PHP's `escapeshellarg()` silently drops multibyte bytes under `LC_CTYPE=C`, which mangled filenames like `Mövenpick.png` to `Mvenpick.png` and caused conversion to silently fail. Closes [#89](https://github.com/plan2net/webp/issues/89).
- TYPO3 14.3 no longer logs an `ext_emconf.php` deprecation notice on every request; `composer.json` now declares `extra.typo3/cms.version` and `extra.typo3/cms.Package.providesPackages` so `PackageManager` recognises the extension as composer-only-capable.

## [14.0.0] - 2026-05-14

### Added

- TYPO3 v14 support; single code path covers v12, v13, and v14.
- Sibling lifecycle: `.webp` siblings next to original images are kept in sync with TYPO3's FAL operations (move, delete, replace). When a storage has a recycler, the sibling follows the file into the recycler so restore keeps the pair intact. Closes [#88](https://github.com/plan2net/webp/issues/88).
- Comprehensive README overhaul: compatibility matrix, configuration summary, troubleshooting checklist, known limitations, and per-option reference for the previously-undocumented `mime_types` and `filter_pattern` keys.
- Regression test suite (unit + functional) and GitHub Actions CI matrix across PHP 8.2/8.3/8.4 × TYPO3 12/13/14.

### Changed

- **BC break for custom converters.** The `Plan2net\Webp\Converter\Converter` interface constructor now takes a second argument: `__construct(string $parameters, Plan2net\Webp\Service\Configuration $configuration)`. Third-party converter implementations need to update their constructor signature accordingly.

### Fixed

- `.webp` source files no longer create phantom rows in `sys_file_processedfile`.
- The listener now normalises `FileReference` inputs to their underlying `File` before the repository lookup — fixes a latent v12/v13 bug where the wrong UID was being queried.
- `FileNameFilter` no longer emits PHP 8+ warnings on invalid filter regex patterns.

[14.5.1]: https://github.com/plan2net/webp/releases/tag/14.5.1
[14.5.0]: https://github.com/plan2net/webp/releases/tag/14.5.0
[14.4.1]: https://github.com/plan2net/webp/releases/tag/14.4.1
[14.4.0]: https://github.com/plan2net/webp/releases/tag/14.4.0
[14.3.0]: https://github.com/plan2net/webp/releases/tag/14.3.0
[14.2.0]: https://github.com/plan2net/webp/releases/tag/14.2.0
[14.1.1]: https://github.com/plan2net/webp/releases/tag/14.1.1
[14.1.0]: https://github.com/plan2net/webp/releases/tag/14.1.0
[14.0.0]: https://github.com/plan2net/webp/releases/tag/14.0.0
