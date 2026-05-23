# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- **Multi-format output**: WebP, AVIF, and JPEG XL siblings can be generated in any combination via a single per-install setting (`formats_enabled = webp,avif,jxl`). Each enabled format has its own converter, parameters, and supported-mime-types list configured in dedicated tabs of the Extension Configuration form (`converter_avif`, `parameters_avif`, `mime_types_avif`; same for `jxl`). `VipsConverter` dispatches to libvips's `webpsave` / `heifsave compression=av1` / `jxlsave`; `MagickConverter` infers the format from the target file suffix; `ExternalConverter` is command-defined. The 4-backend × 3-format support matrix is documented in the README. Each enabled format gets its own `sys_file_processedfile` row; TYPO3's "Remove Temporary Assets" maintenance task cleans all rows uniformly.
- New `Plan2net\Webp\Converter\MultiFormatConverter` interface for converters that accept an `OutputFormat` argument. Custom 3rd-party converters that implement only the legacy `Converter` interface keep working for the WebP slot — no migration required. To opt into AVIF/JXL, implement `MultiFormatConverter` or extend the updated `AbstractConverter` (which provides both interfaces).
- New `Plan2net\Webp\Format\OutputFormat` enum (`Webp`, `Avif`, `Jxl`) carried through the orchestrator, queue rows, failed-attempts cache, and `webp:diagnose` reporting.
- `webp:diagnose --format=<webp|avif|jxl>` filter restricts the report to one format. The delivery probe sends four `Accept` headers (avif/jxl/webp/`*/*`) and verifies the server returns the highest-priority format the install actually generates. Storages, converter health, async pipeline, failed-attempts cache, and per-file deep-dive all report per format.
- `webp:process-queue --folder=<path>` sweeps the configured folder for each enabled format.

### Changed

- `tx_webp_queue` and `tx_webp_failed` gain a `format VARCHAR(8) NOT NULL DEFAULT 'webp'` column; the queue's unique-dedup index is reshaped to include it. Existing rows interpret correctly with no data migration — run the TYPO3 database analyzer after upgrade.
- Default `filter_pattern` now matches `.avif` and `.jxl` siblings as well as `.webp`. Custom values are left untouched; admins who pinned a webp-only regex keep their value.
- Internal class renames for format-symmetric architecture (all classes are internal to the extension; no settings keys, DB table names, CLI command names, or scheduler task class names change):
  - `Plan2net\Webp\Service\Webp` → `Plan2net\Webp\Service\SiblingGenerator`
  - `Plan2net\Webp\Service\WebpSiblingFile` → `Plan2net\Webp\Service\SiblingFile`
  - `Plan2net\Webp\Service\StorageWebpMode` → `Plan2net\Webp\Service\StorageSiblingMode`
  - `Plan2net\Webp\Domain\Queue\WebpQueueRepository` → `Plan2net\Webp\Domain\Queue\ConversionQueueRepository`
  - `Plan2net\Webp\Domain\Queue\WebpQueueEntry` → `Plan2net\Webp\Domain\Queue\ConversionQueueEntry`
  - `Plan2net\Webp\Command\ProcessWebpQueueCommand` → `Plan2net\Webp\Command\ProcessConversionQueueCommand` (CLI name `webp:process-queue` unchanged)
  - `Plan2net\Webp\Core\Filter\FileNameFilter::filterWebpFiles()` → `filterSiblingFiles()`
  - `Plan2net\Webp\Service\Configuration::isHideWebp()` → `isHideSiblings()` (config key `hide_webp` unchanged)
- `SYS/mediafile_ext` is now extended to include `avif` and `jxl` (in addition to `webp`) so FAL's source-folder publish path accepts the new sibling extensions.
- Sibling lifecycle (move / rename / replace / delete) now covers all three formats. Any on-disk `.avif` or `.jxl` sibling follows its original alongside the existing `.webp` handling.

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

[14.4.1]: https://github.com/plan2net/webp/releases/tag/14.4.1
[14.4.0]: https://github.com/plan2net/webp/releases/tag/14.4.0
[14.3.0]: https://github.com/plan2net/webp/releases/tag/14.3.0
[14.2.0]: https://github.com/plan2net/webp/releases/tag/14.2.0
[14.1.1]: https://github.com/plan2net/webp/releases/tag/14.1.1
[14.1.0]: https://github.com/plan2net/webp/releases/tag/14.1.0
[14.0.0]: https://github.com/plan2net/webp/releases/tag/14.0.0
