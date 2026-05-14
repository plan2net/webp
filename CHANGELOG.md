# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Opt-in **async conversion mode** (`async = 1`): WebP conversions are queued in a new `tx_webp_queue` table and processed out-of-band by a TYPO3 Scheduler task. Closes [#17](https://github.com/plan2net/webp/issues/17).
- New CLI command `webp:process-queue` with optional `--folder=PATH` argument to convert images in non-FAL folders (e.g., `typo3temp/assets/online_media/`). Closes [#73](https://github.com/plan2net/webp/issues/73).
- New `async_throttle_ms` configuration to space conversions out with randomized jitter, preventing thundering-herd CPU/IO.

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

[14.0.0]: https://github.com/plan2net/webp/releases/tag/14.0.0
