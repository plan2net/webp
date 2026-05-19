<?php

declare(strict_types=1);

namespace Plan2net\Webp\Converter;

use Jcupitt\Vips\Image;
use Plan2net\Webp\Service\Configuration;

final class VipsConverter extends AbstractConverter
{
    public function __construct(string $parameters, Configuration $configuration)
    {
        $ffiEnable = \strtolower((string) \ini_get('ffi.enable'));
        $ffiGloballyEnabled = \extension_loaded('ffi')
            && '' !== $ffiEnable
            && 'preload' !== $ffiEnable
            && false !== \filter_var($ffiEnable, \FILTER_VALIDATE_BOOLEAN, \FILTER_NULL_ON_FAILURE)
            && (bool) \filter_var($ffiEnable, \FILTER_VALIDATE_BOOLEAN);

        if (!$ffiGloballyEnabled || !\class_exists(Image::class)) {
            throw new \InvalidArgumentException('libvips not available: ext-ffi must be loaded with `ffi.enable=true` (not `preload` — jcupitt/vips does not support preloading), the libvips shared library must be on the host, and `composer require jcupitt/vips` must have run. Pick a different converter otherwise.');
        }

        parent::__construct($parameters, $configuration);
    }

    public function convert(string $originalFilePath, string $targetFilePath): void
    {
        $loadOptions = self::isAnimatedSource($originalFilePath) ? ['n' => -1] : [];
        $image = Image::newFromFile($originalFilePath, $loadOptions);
        $image->writeToFile($targetFilePath, VipsOptionParser::parse($this->parameters));

        if (!@\is_file($targetFilePath)) {
            throw new \RuntimeException(\sprintf('File "%s" was not created!', $targetFilePath));
        }
    }

    private static function isAnimatedSource(string $path): bool
    {
        return \str_ends_with(\strtolower($path), '.gif');
    }
}
