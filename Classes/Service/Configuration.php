<?php
declare(strict_types=1);

namespace Plan2net\Webp\Service;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class Configuration
 *
 * @package Plan2net\Webp\Service
 * @author Wolfgang Klinger <wk@plan2.net>
 */
class Configuration implements SingletonInterface
{
    /**
     * @var array
     */
    protected static $configuration = [];

    /**
     * Returns the whole extension configuration or a specific key
     *
     * @return array|string|null
     */
    public static function get(?string $key = null)
    {
        if (empty(self::$configuration)) {
            try {
                self::$configuration = GeneralUtility::makeInstance(ExtensionConfiguration::class)
                    ->get('webp');
            } catch (\Exception $e) {
                // Ignore
            }
        }

        if (!empty($key)) {
            if (isset(self::$configuration[$key])) {
                return (string)self::$configuration[$key];
            }

            return null;
        }

        return self::$configuration;
    }
}
