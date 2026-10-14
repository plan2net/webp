# Troubleshooting

[← README](../README.md)

## Diagnosing your installation

The `webp:diagnose` CLI command checks the whole delivery chain, for every enabled output format, and points at the first link that fails.

```bash
vendor/bin/typo3 webp:diagnose                              # health check
vendor/bin/typo3 webp:diagnose --url=https://example.com    # also probe webserver delivery
vendor/bin/typo3 webp:diagnose --file=42                    # also investigate one file
vendor/bin/typo3 webp:diagnose --format=avif                # limit the report to one format
```

For each enabled format it reports:

- Storages: mode, driver, number of siblings per format, and leftover rows whose driver is not registered.
- Converter: class, whether the delegate for each format is there (`convert -list format` for Magick; `vips_foreign_find_save` for libvips), how the parameters parse.
- Async pipeline: queue size and age of the oldest entry per format, state of the scheduler task.
- Failed-conversion cache: totals per format, recent rows, the most common config hash.
- Delivery probe (`--url=…`): four `Accept` HEAD requests (avif / jxl / webp / `*/*`) compared against `expectedTopFormat`, plus `Vary: Accept`.
- One file in detail (`--file=<uid>`): metadata, which siblings exist per format (next to the original and in the processed folder), and failed attempts per format.

**Limitation:** the probe runs from this machine. Your CDN can behave differently at the edge. For the most accurate result, run the probe from a host inside your CDN's pull zone.

Useful flags:

| Flag | Purpose |
|---|---|
| `--format=<webp\|avif\|jxl>` | Limit the report to one output format |
| `--insecure` | Disable TLS certificate verification on the HTTP probe, for self-signed or otherwise untrusted certs |
| `--probe-timeout=<sec>` | HTTP probe timeout (default: 10) |

## Common problems

TYPO3's log (`var/log/typo3_*.log` by default) has an entry for every conversion problem. Start there.

| Symptom                                                | Likely cause                                                                 |
|--------------------------------------------------------|------------------------------------------------------------------------------|
| No sibling files appear in `_processed_/`              | Converter binary lacks the delegate for the enabled format(s) (`webp:diagnose` reports this), or `mime_types_<format>` excludes the source |
| Files are bigger than the original                     | Automatically removed and **not retried** with the same configuration         |
| Output renders darker / off-colour                     | `$GLOBALS['TYPO3_CONF_VARS']['GFX']['processor_colorspace']` (e.g. `sRGB`)    |
| Apache rewrite returns 403/404                         | [`%{REQUEST_FILENAME}` doesn't resolve](Webserver.md#when-request_filename-doesnt-resolve) |
| File still served as JPEG after a successful generation | Webserver rewrite rule missing or shadowed by another rule                  |
| Only one format is served when several are enabled     | Rewrite rule lists the formats in the wrong priority order: AVIF should come before WebP, see [Webserver configuration](Webserver.md#which-blocks-do-i-need) |
| Sibling left behind after deleting the source          | None; fixed in 14.0.0, upgrade                                               |
| Large/high-resolution originals fail or time out, or produce no sibling | See [Very large or high-resolution originals](#very-large-or-high-resolution-originals) |
| WebP/AVIF looks softer or blurrier than the JPEG       | See [Sharp output](#sharp-output)                                            |

After you change `processor_colorspace`, remove the processed files in the Maintenance backend module (*System → Maintenance → Remove Temporary Assets* on TYPO3 v14; *Admin Tools → Maintenance* on v12/v13), so the change also applies to existing images.

## Very large or high-resolution originals

Originals of several megabytes (for example photos of 4000px and more), rendered at several responsive widths, are where conversion fails most often. ImageMagick can run out of memory or hit the request time limit. At the default quality, the largest WebP can also come out *bigger* than the source. The extension then drops it, and that variant gets no sibling at all. Three settings help, and they work well together:

- **Use the [`VipsConverter`](Configuration.md#using-libvips-natively).** libvips converts large images with much less memory and 2 to 3 times faster than ImageMagick. This removes the usual memory and timeout failures on big originals.
- **Turn on [`async`](Operations.md#async-mode).** Conversions move from the page render to a scheduler worker, so a slow or heavy conversion can never fail or delay the frontend response.
- **Configure a [width-to-quality curve](Compression.md#compress-larger-variants-harder).** Large variants are compressed harder. They stay smaller than the original and get a sibling, instead of being rejected for being bigger than the source.

## Sharp output

TYPO3 core sharpens generated JPEGs, but not WebP or AVIF. So images converted *directly* to WebP can look softer. This extension avoids that: it converts the **processed image variant**, the cropped, scaled and (for JPEG) sharpened file that TYPO3 already made. The sibling keeps that sharpening and doesn't start again from the soft original.

If you want more sharpening, `MagickConverter` passes its [`parameters`](Configuration.md#parameters) straight to ImageMagick, so you can add a sharpen operator per mime type, for example `image/jpeg::-quality 85 -unsharp 0x0.75+0.75+0.008`. `VipsConverter` only applies encoder save options and has no sharpening parameter. Choose `MagickConverter` for that format if you need sharpening.

## Known limitations

- **Animated GIFs.** Only the libvips routes keep the animation. `VipsConverter` does it automatically. The `vips` CLI route needs `%s[n=-1]` on the GIF source argument (see [Using an external binary](Configuration.md#using-an-external-binary)). `MagickConverter`, `PhpGdConverter` and `cwebp`-based configurations write a single frame. For AVIF and JPEG XL siblings, animation depends on the converter, and the test suite doesn't cover it.
- **The image processor needs the right delegate for each enabled format.** WebP needs the libwebp delegate, AVIF needs libheif with an AV1 encoder, JPEG XL needs libjxl. See [Requirements](Formats.md#requirements).
- **Moves between two storages are not handled.** If you move a file from one storage to another, its sibling stays behind in the source storage. The target storage gets a new sibling on the next render. Moves inside one storage work correctly.
- **`use_system_settings` only applies to `MagickConverter`.** `PhpGdConverter` and external binaries ignore it.
