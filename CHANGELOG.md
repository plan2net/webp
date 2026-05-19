# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

[14.4.0]: https://github.com/plan2net/webp/releases/tag/14.4.0
[14.3.0]: https://github.com/plan2net/webp/releases/tag/14.3.0
[14.2.0]: https://github.com/plan2net/webp/releases/tag/14.2.0
[14.1.1]: https://github.com/plan2net/webp/releases/tag/14.1.1
[14.1.0]: https://github.com/plan2net/webp/releases/tag/14.1.0
[14.0.0]: https://github.com/plan2net/webp/releases/tag/14.0.0
