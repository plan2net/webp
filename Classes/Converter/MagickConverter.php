<?php

declare(strict_types=1);

namespace Plan2net\Webp\Converter;

use Plan2net\Webp\Format\OutputFormat;
use TYPO3\CMS\Core\Imaging\GraphicalFunctions;
use TYPO3\CMS\Core\Utility\CommandUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Uses the builtin TYPO3 graphical functions (imagemagick, graphicsmagick).
 */
final class MagickConverter extends AbstractConverter
{
    public function convertTo(string $originalFilePath, string $targetFilePath, OutputFormat $format): void
    {
        if (!\str_ends_with(\strtolower($targetFilePath), $format->suffix())) {
            throw new \InvalidArgumentException(\sprintf('MagickConverter: target "%s" does not match expected suffix "%s" for format %s', $targetFilePath, $format->suffix(), $format->value));
        }

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
            throw new \RuntimeException(\sprintf('File "%s" was not created: %s', $targetFilePath, $result ?: \sprintf('maybe missing %s delegate?', $format->value)));
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
