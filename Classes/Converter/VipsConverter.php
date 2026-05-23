<?php

declare(strict_types=1);

namespace Plan2net\Webp\Converter;

use Jcupitt\Vips\Exception as VipsException;
use Jcupitt\Vips\FFI as VipsFFI;
use Jcupitt\Vips\Image;
use Plan2net\Webp\Converter\Exception\UnsupportedFormatException;
use Plan2net\Webp\Format\OutputFormat;
use Plan2net\Webp\Service\Configuration;

final class VipsConverter extends AbstractConverter
{
    public function __construct(string $parameters, Configuration $configuration)
    {
        if (!self::isFfiGloballyEnabled() || !\class_exists(Image::class)) {
            throw new \InvalidArgumentException('libvips not available: ext-ffi must be loaded with `ffi.enable=true` (not `preload` — jcupitt/vips does not support preloading), the libvips shared library must be on the host, and `composer require jcupitt/vips` must have run. Pick a different converter otherwise.');
        }

        parent::__construct($parameters, $configuration);
    }

    public function convertTo(string $originalFilePath, string $targetFilePath, OutputFormat $format): void
    {
        if ('' === (string) VipsFFI::vips()->vips_foreign_find_save('probe' . $format->suffix())) {
            throw new UnsupportedFormatException(\sprintf('libvips on this host lacks a "%s" saver. Install libheif (AVIF) or libjxl (JXL) and rebuild libvips, or pick a different converter for this format.', $format->suffix()));
        }

        try {
            $loadOptions = self::isAnimatedSource($originalFilePath) ? ['n' => -1] : [];
            $image = Image::newFromFile($originalFilePath, $loadOptions);
            $options = VipsOptionParser::parse($this->parameters);

            match ($format) {
                OutputFormat::Webp => $image->webpsave($targetFilePath, $options),
                // heifsave defaults to HEIC (H.265); compression=av1 selects AVIF.
                OutputFormat::Avif => $image->heifsave($targetFilePath, ['compression' => 'av1'] + $options),
                OutputFormat::Jxl => $image->jxlsave($targetFilePath, $options),
            };
        } catch (VipsException $exception) {
            throw new \RuntimeException(\sprintf('libvips conversion of "%s" failed: %s', $originalFilePath, $exception->getMessage()), 0, $exception);
        }

        if (!@\is_file($targetFilePath)) {
            throw new \RuntimeException(\sprintf('File "%s" was not created!', $targetFilePath));
        }
    }

    private static function isFfiGloballyEnabled(): bool
    {
        if (!\extension_loaded('ffi')) {
            return false;
        }

        $ffiEnable = \strtolower((string) \ini_get('ffi.enable'));
        if ('' === $ffiEnable || 'preload' === $ffiEnable) {
            return false;
        }

        return true === \filter_var($ffiEnable, \FILTER_VALIDATE_BOOLEAN, \FILTER_NULL_ON_FAILURE);
    }

    private static function isAnimatedSource(string $path): bool
    {
        return \str_ends_with(\strtolower($path), '.gif');
    }
}
