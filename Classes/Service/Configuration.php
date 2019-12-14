<?php
declare(strict_types=1);

namespace Plan2net\Webp\Service;

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
     * @param string|null $key
     * @return array|string|null
     */
    public static function get(?string $key = null)
    {
        try {
            if (version_compare(TYPO3_version, '9.5', '>=')) {
                self::$configuration = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class)->get('webp');
            } else {
                // @deprecated, remove when dropping support for 8.7
                self::$configuration = (array)unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['webp'], [false]);
            }
        } catch (\Exception $e) {}

        if (!empty($key)) {
            if (isset(self::$configuration[$key])) {
                return (string)self::$configuration[$key];
            }

            return null;
        }

        return self::$configuration;
    }
}
