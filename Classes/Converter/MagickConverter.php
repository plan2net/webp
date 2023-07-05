<?php

declare(strict_types=1);

namespace Plan2net\Webp\Converter;

use Plan2net\Webp\Service\Configuration;
use TYPO3\CMS\Core\Imaging\GraphicalFunctions;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Uses the builtin TYPO3 graphical functions (imagemagick, graphicsmagick).
 */
final class MagickConverter extends AbstractConverter
{
    public function convert(string $originalFilePath, string $targetFilePath): void
    {
        $parameters = $this->parameters;
        if ((bool) Configuration::get('use_system_settings')) {
            $parameters .= ' ' . $GLOBALS['TYPO3_CONF_VARS']['GFX']['processor_stripColorProfileCommand'];
            $parameters = trim($parameters);
        }

        $result = $this->getGraphicalFunctionsObject()->imageMagickExec(
            $originalFilePath,
            $targetFilePath,
            $parameters
        );

        if (!@\is_file($targetFilePath)) {
            throw new \RuntimeException(\sprintf('File "%s" was not created: %s!', $targetFilePath, $result ?: 'maybe missing support for webp?'));
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
}
