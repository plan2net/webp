<?php
declare(strict_types=1);

namespace Plan2net\Webp\Adapter;

use InvalidArgumentException;
use RuntimeException;
use TYPO3\CMS\Core\Utility\CommandUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class ExternalAdapter
 *
 * @package Plan2net\Webp\Adapter
 * @author Wolfgang Klinger <wk@plan2.net>
 */
class ExternalAdapter implements AdapterInterface
{
    /**
     * @var string
     */
    protected $parameters;

    /**
     * @param string $parameters
     * @throws InvalidArgumentException
     */
    public function __construct(string $parameters)
    {
        if (substr_count($parameters, '%s') !== 2) {
            throw new InvalidArgumentException('Command string is invalid, supply 2 string placeholders!');
        }
        $binary = explode(' ', $parameters)[0];
        if (!is_executable($binary)) {
            throw new InvalidArgumentException(sprintf('Binary "%s" is not executable!', $binary));
        }

        $this->parameters = $parameters;
    }

    /**
     * @inheritdoc
     */
    public function convert(string $originalFilePath, string $targetFilePath)
    {
        $command = escapeshellcmd(sprintf($this->parameters, $originalFilePath, $targetFilePath));
        CommandUtility::exec($command);
        GeneralUtility::fixPermissions($targetFilePath);

        if (!@is_file($targetFilePath)) {
            throw new RuntimeException(sprintf('File "%s" could not be created!', $targetFilePath));
        }
    }
}
