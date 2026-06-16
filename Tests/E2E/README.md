# E2E tests

Black-box end-to-end coverage of two things the functional tests can't reach:

1. **Sibling generation** — that a real TYPO3 install with `formats_enabled = webp,avif,jxl` actually writes all three sibling files to disk when an image is processed.
2. **Webserver content negotiation** — that the documented nginx, Apache and Caddy rewrite recipes in [README.md](../../README.md) actually serve the right format per `Accept` header against a real `nginx` / `apache2` / `caddy` daemon (not just our `webp:diagnose` HTTP probe).

## Running locally

Requires a Linux host with `nginx`, `apache2`, `caddy`, `imagemagick`, `libvips-tools`, `libheif1`, `libjxl-tools`, PHP 8.2+ with `pdo_sqlite` + `ffi`, and Composer. Each webserver is skipped cleanly if its binary is absent.

```sh
bash Tests/E2E/run.sh
```

The runner is idempotent — it tears down any previous instance under `/tmp/plan2net-webp-e2e/` before building a fresh one.

To target a specific TYPO3 version (default: `^14.3`):

```sh
TYPO3_VERSION='^13.4' bash Tests/E2E/run.sh
```

## What the runner does

1. Composes a minimal TYPO3 instance into `/tmp/plan2net-webp-e2e/instance/`. Requires `typo3/cms-base`, `typo3/cms-fluid-styled-content`, `jcupitt/vips`, and the current branch of `plan2net/webp` (as a path repository).
2. Runs `vendor/bin/typo3 setup` (v13/v14) or `install:setup` (v12) non-interactively with SQLite.
3. Drops a fixture JPEG into `fileadmin/`, writes the multi-format extension config into `config/system/settings.php`.
4. Boots PHP's built-in server (`php -S`) and curls the root page. `IMG_RESOURCE` rendering triggers FAL processing, and the `AfterFileProcessing` listener writes the `.webp`, `.avif`, and `.jxl` siblings next to the ProcessedFile on disk.
5. Asserts each sibling exists (or is skipped cleanly when the underlying delegate is missing on this host).
6. For **each** webserver (nginx, Apache, then Caddy):
   - Generates the negotiation config by running `vendor/bin/typo3 webp:webserver-config --server=X --scope=KEY` for each required scope and injecting the output into marker slots in the `.conf` template. This proves the E2E exercises the *generated* config, not a hand-written recipe.
   - Starts the daemon on `127.0.0.1:8090` against the test instance with that generated config.
   - Sends four `Accept`-header probes via `curl`, asserting the correct `Content-Type` for each (avif / jxl / webp / `*/*`), plus a `Vary: Accept` assertion on the negotiated response.
   - Stops the daemon.

Exit code is non-zero on any failed assertion. The runner prints what passed and what failed; on failure it dumps the relevant access/error log.

## CI

`.github/workflows/e2e.yml` runs this against the PHP × TYPO3 matrix on every push to `master` and on pull requests. Each cell takes ~3–4 min.
