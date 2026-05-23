<?php

declare(strict_types=1);

namespace Plan2net\Webp\Converter;

use Plan2net\Webp\Format\OutputFormat;
use Plan2net\Webp\Service\Configuration;
use TYPO3\CMS\Core\Utility\CommandUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Uses an external binary (e.g. cwebp).
 */
final class ExternalConverter extends AbstractConverter
{
    /**
     * @throws \InvalidArgumentException
     */
    public function __construct(string $parameters, Configuration $configuration)
    {
        if (2 !== \substr_count($parameters, '%s')) {
            throw new \InvalidArgumentException('Command string is invalid, supply 2 string (%s) placeholders!');
        }
        $binary = \explode(' ', $parameters)[0];
        if (!\is_executable($binary)) {
            throw new \InvalidArgumentException(\sprintf('Binary "%s" is not executable!', $binary));
        }

        parent::__construct($parameters, $configuration);
    }

    public function convertTo(string $originalFilePath, string $targetFilePath, OutputFormat $format): void
    {
        $silent = $this->configuration->isSilent();
        $command = \sprintf(
            \escapeshellcmd($this->parameters),
            self::quoteShellArgument($originalFilePath),
            self::quoteShellArgument($targetFilePath)
        ) . ($silent ? ' >/dev/null 2>&1' : '');
        CommandUtility::exec($command);
        GeneralUtility::fixPermissions($targetFilePath);

        if (!@\is_file($targetFilePath)) {
            throw new \RuntimeException(\sprintf('File "%s" was not created!', $targetFilePath));
        }
    }

    /**
     * Byte-preserving POSIX shell quoting. PHP's escapeshellarg() is locale-aware
     * and silently drops multibyte bytes when LC_CTYPE is C/POSIX, mangling
     * filenames with umlauts before they reach the binary.
     */
    private static function quoteShellArgument(string $argument): string
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            return \escapeshellarg($argument);
        }

        return "'" . \str_replace("'", "'\\''", $argument) . "'";
    }
}
