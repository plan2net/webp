# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [14.0.0] - 2026-05-13

### Added

- TYPO3 v14 support; single code path covers v12, v13, and v14.
- Regression test suite (unit + functional) and GitHub Actions CI matrix across PHP 8.2/8.3/8.4 × TYPO3 12/13/14.
- README sections for the previously-undocumented `mime_types` and `filter_pattern` configuration keys.
- Sibling lifecycle: `.webp` siblings next to original images are kept in sync with TYPO3's FAL operations (move, delete, replace). When a storage has a recycler, the sibling follows the file into the recycler so restore keeps the pair intact. Closes [#88](https://github.com/plan2net/webp/issues/88).

### Changed

- **BC break for custom converters.** The `Plan2net\Webp\Converter\Converter` interface constructor now takes a second argument: `__construct(string $parameters, Plan2net\Webp\Service\Configuration $configuration)`. Third-party converter implementations need to update their constructor signature accordingly.

### Fixed

- `.webp` source files no longer create phantom rows in `sys_file_processedfile`.
- The listener now normalises `FileReference` inputs to their underlying `File` before the repository lookup — fixes a latent v12/v13 bug where the wrong UID was being queried.
- `FileNameFilter` no longer emits PHP 8+ warnings on invalid filter regex patterns.

[14.0.0]: https://github.com/plan2net/webp/releases/tag/14.0.0
