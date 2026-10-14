# Formats and converters

[← README](../README.md)

## The three formats

| Format       | Released | Coverage (May 2026)¹ | Typical size² | Notes |
|--------------|----------|----------------------|---------------|-------|
| **WebP**     | 2010     | ~95.6%               | −25 to −34% vs JPEG; −26% vs PNG | Mature; broadest browser support |
| **AVIF**     | 2019     | ~94%                 | ~−20 to −30% vs WebP at equal SSIM | AV1-based; supported by Chrome 85+, Firefox 93+, Safari 16+ |
| **JPEG XL**  | 2022     | ~17%                 | Comparable to AVIF, often better at lossless | Supported by Safari 17+; coverage growing |

- ¹ caniuse.com, StatCounter data, April 2026.
- ² Per Google reference numbers and AVIF benchmark suites; real-world savings vary with content.

All three support lossy and lossless modes, transparency, and ICC color profiles. WebP and the libvips path also support animation. For AVIF and JPEG XL, animation depends on the converter you choose.

Smaller images improve Core Web Vitals directly. Largest Contentful Paint (LCP) gains the most, because the image has fewer bytes but the same size on screen. Saving 25 to 34% of the bandwidth adds up on pages with many images and on mobile connections.

## Which converter writes which format

Pick a converter for each format that matches what is installed on the host. The four built-in converters cover the formats like this:

| Converter           | webp | avif | jxl |
|---------------------|------|------|-----|
| `VipsConverter`     | ✓    | ✓¹   | ✓²  |
| `MagickConverter`   | ✓    | ✓³   | ✓⁴  |
| `ExternalConverter` | ✓    | ✓⁵   | ✓⁶  |
| `PhpGdConverter`    | ✓    | ✗    | ✗   |

- ¹ libvips built with libheif (AV1 encoder)
- ² libvips built with libjxl
- ³ ImageMagick with libheif AV1 delegate
- ⁴ ImageMagick 7+ with libjxl delegate
- ⁵ command-defined, e.g. `avifenc`
- ⁶ command-defined, e.g. `cjxl`

`webp:diagnose` checks for each format whether the delegate is there. See [Diagnosing your installation](Troubleshooting.md#diagnosing-your-installation).

## Requirements

You need an image converter on the host that can write the formats you enable. Pick one of the four converters from the table above.

For the default install, which writes WebP only, you need one of these:

- **ImageMagick** or **GraphicsMagick** compiled with the WebP delegate
- **PHP GD** with the `IMG_WEBP` flag at runtime
- **libvips** in-process through [`jcupitt/vips`](https://packagist.org/packages/jcupitt/vips) and PHP `ext-ffi`
- any other external WebP encoder, such as [`cwebp`](https://developers.google.com/speed/webp/docs/cwebp)

For **AVIF** and **JPEG XL** you also need one of these:

- **libvips** built with libheif (AVIF) and/or libjxl (JPEG XL). This is the simplest way: one `apt install libvips-tools libheif1 libjxl-tools` covers both, plus `composer require jcupitt/vips`.
- **ImageMagick** with the matching delegates: `libheif` with the AV1 encoder for AVIF, `libjxl` for JPEG XL.
- **external encoders** such as `avifenc` (libavif) and `cjxl` (libjxl), called through `ExternalConverter`.

Check your existing setup:

```sh
# GraphicsMagick — should return "yes" for any format you've enabled
gm version | grep -iE "webp|heif|jxl"

# ImageMagick — listed AVIF / JXL should be flagged "rw" (read+write)
convert -list format | grep -iE "WEBP|AVIF|JXL"
```

`webp:diagnose` runs the same checks for the converter you configured for each format. See [Diagnosing your installation](Troubleshooting.md#diagnosing-your-installation).

## What it costs

- **Extra CPU.** Every processed image is processed once more for each enabled format. This happens once per image and format, because the result is cached on disk, but it adds time to the first render. The AVIF and JPEG XL encoders are slower than WebP, so plan for more CPU when you turn them on.
- **Extra disk.** Each enabled format adds one sibling per source image. Typical sizes compared to the original:
  - WebP: about 65 to 75% (Google's reference numbers: lossy WebP is 25 to 34% smaller than JPEG; lossless is 26% smaller than PNG)
  - AVIF: about 45 to 55% at equal SSIM
  - JPEG XL: similar to AVIF, often better on lossless content

  With `convert_all = 1` this applies to every source image, not only to processed variants, and it multiplies by the number of enabled formats.
