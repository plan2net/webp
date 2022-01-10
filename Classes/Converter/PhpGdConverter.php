<?php

declare(strict_types=1);

namespace Plan2net\Webp\Converter;

use RuntimeException;
use TYPO3\CMS\Core\Imaging\GraphicalFunctions;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use function defined;
use function function_exists;
use function imageistruecolor;
use function imagepalettetotruecolor;
use function imagetypes;
use function imagewebp;
use function is_file;
use function preg_match;
use function sprintf;

/**
 * Class PhpGdConverter
 * Uses the php gd library to generate webp images
 *
 * @author André Schließer <a.schliesser@zeroseven.de>
 * @author Wolfgang Klinger <wk@plan2.net>
 */
final class PhpGdConverter extends AbstractConverter
{
    private const DEFAULT_QUALITY = 80;

    public function convert(string $originalFilePath, string $targetFilePath): void
    {
        if (!$this->gdSupportsWebp()) {
            throw new RuntimeException(sprintf('File "%s" was not created: GD is not active or does not support webp!', $targetFilePath));
        }

        $image = $this->getImage($originalFilePath);
        $result = imagewebp($image, $targetFilePath, $this->getQuality());

        if (!$result || !@is_file($targetFilePath)) {
            throw new RuntimeException(sprintf('File "%s" was not created!', $targetFilePath));
        }
    }

    private function getGraphicalFunctionsObject(): GraphicalFunctions
    {
        static $graphicalFunctionsObject = null;

        if (null === $graphicalFunctionsObject) {
            /** @var GraphicalFunctions $graphicalFunctionsObject */
            $graphicalFunctionsObject = GeneralUtility::makeInstance(GraphicalFunctions::class);
        }

        return $graphicalFunctionsObject;
    }

    private function gdSupportsWebp(): bool
    {
        return function_exists('imagewebp')
            && defined('IMG_WEBP')
            && (imagetypes() & IMG_WEBP) === IMG_WEBP;
    }

    /**
     * @return resource
     */
    private function getImage(string $originalFilePath)
    {
        $image = $this->getGraphicalFunctionsObject()->imageCreateFromFile($originalFilePath);
        // Convert CMYK to RGB
        if (!imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        return $image;
    }

    private function getQuality(): int
    {
        preg_match('/quality(\s|=)(\d{1,3})/', $this->parameters, $matches);

        if (isset($matches[2]) && (int) $matches[2] > 0) {
            return (int) $matches[2];
        }

        return self::DEFAULT_QUALITY;
    }
}
