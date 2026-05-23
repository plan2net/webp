<?php

declare(strict_types=1);

namespace Plan2net\Webp\Converter;

use Plan2net\Webp\Converter\Exception\UnsupportedFormatException;
use Plan2net\Webp\Format\OutputFormat;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Imaging\GifBuilder;

/**
 * Uses the php gd library to generate webp images.
 */
final class PhpGdConverter extends AbstractConverter
{
    private const DEFAULT_QUALITY = 80;

    public function convertTo(string $originalFilePath, string $targetFilePath, OutputFormat $format): void
    {
        if (OutputFormat::Webp !== $format) {
            throw new UnsupportedFormatException(\sprintf('PhpGdConverter cannot produce %s — PHP GD only supports webp.', $format->value));
        }

        if (!$this->gdSupportsWebp()) {
            throw new \RuntimeException(\sprintf('File "%s" was not created: GD is not active or does not support webp!', $targetFilePath));
        }

        $image = $this->getImage($originalFilePath);
        $result = \imagewebp($image, $targetFilePath, $this->getQuality());

        if (!$result || !@\is_file($targetFilePath)) {
            throw new \RuntimeException(\sprintf('File "%s" was not created!', $targetFilePath));
        }
    }

    private function gdSupportsWebp(): bool
    {
        return \function_exists('imagewebp')
            && \defined('IMG_WEBP')
            && (\imagetypes() & IMG_WEBP) === IMG_WEBP;
    }

    /**
     * @return \GdImage
     */
    private function getImage(string $originalFilePath)
    {
        $image = GeneralUtility::makeInstance(GifBuilder::class)->imageCreateFromFile($originalFilePath);
        // Convert CMYK to RGB
        if (!\imageistruecolor($image)) {
            \imagepalettetotruecolor($image);
        }

        return $image;
    }

    private function getQuality(): int
    {
        \preg_match('/\\bquality[\\s=](\\d{1,3})\\b/', $this->parameters, $matches);

        if (isset($matches[1]) && (int) $matches[1] > 0) {
            return (int) $matches[1];
        }

        return self::DEFAULT_QUALITY;
    }
}
