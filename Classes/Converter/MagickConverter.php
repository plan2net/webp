<?php

declare(strict_types=1);

namespace Plan2net\Webp\Converter;

use TYPO3\CMS\Core\Imaging\GraphicalFunctions;
use TYPO3\CMS\Core\Utility\CommandUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Uses the builtin TYPO3 graphical functions (imagemagick, graphicsmagick).
 */
final class MagickConverter extends AbstractConverter
{
    public function convert(string $originalFilePath, string $targetFilePath): void
    {
        $parameters = $this->parameters;
        if ($this->configuration->isUseSystemSettings()) {
            $parameters .= ' ' . $this->parseStripColorProfileCommand();
            $parameters = trim($parameters);
        }

        $result = GeneralUtility::makeInstance(GraphicalFunctions::class)->imageMagickExec(
            $originalFilePath,
            $targetFilePath,
            $parameters
        );

        if (!@\is_file($targetFilePath)) {
            throw new \RuntimeException(\sprintf('File "%s" was not created: %s!', $targetFilePath, $result ?: 'maybe missing support for webp?'));
        }
    }

    /**
     * @see https://typo3.org/security/advisory/typo3-core-sa-2024-002
     */
    private function parseStripColorProfileCommand(): string
    {
        if (is_string($GLOBALS['TYPO3_CONF_VARS']['GFX']['processor_stripColorProfileCommand'] ?? null)) {
            return $GLOBALS['TYPO3_CONF_VARS']['GFX']['processor_stripColorProfileCommand'];
        }

        return implode(
            ' ',
            array_map(
                CommandUtility::escapeShellArgument(...),
                $GLOBALS['TYPO3_CONF_VARS']['GFX']['processor_stripColorProfileParameters'] ?? [],
            ),
        );
    }
}
