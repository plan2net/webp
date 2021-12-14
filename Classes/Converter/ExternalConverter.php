<?php
declare(strict_types=1);

namespace Plan2net\Webp\Converter;

use InvalidArgumentException;
use Plan2net\Webp\Service\Configuration;
use RuntimeException;
use TYPO3\CMS\Core\Utility\CommandUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class ExternalAdapter
 * Uses an external binary (e.g. cwebp)
 *
 * @package Plan2net\Webp\Adapter
 * @author Wolfgang Klinger <wk@plan2.net>
 */
final class ExternalConverter extends AbstractConverter
{
    /**
     * @throws InvalidArgumentException
     */
    public function __construct(string $parameters)
    {
        if (substr_count($parameters, '%s') !== 2) {
            throw new InvalidArgumentException('Command string is invalid, supply 2 string (%s) placeholders!');
        }
        $binary = explode(' ', $parameters)[0];
        if (!is_executable($binary)) {
            throw new InvalidArgumentException(sprintf('Binary "%s" is not executable!', $binary));
        }

        parent::__construct($parameters);
    }

    public function convert(string $originalFilePath, string $targetFilePath): void
    {
        $silent = (bool)Configuration::get('silent');
        $command = sprintf(
                escapeshellcmd($this->parameters),
                CommandUtility::escapeShellArgument($originalFilePath),
                CommandUtility::escapeShellArgument($targetFilePath)
            ) . ($silent ? ' >/dev/null 2>&1' : '');
        CommandUtility::exec($command);
        GeneralUtility::fixPermissions($targetFilePath);

        if (!@is_file($targetFilePath)) {
            throw new RuntimeException(sprintf('File "%s" was not created!', $targetFilePath));
        }
    }
}
