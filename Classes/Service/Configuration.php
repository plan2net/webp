<?php
declare(strict_types=1);

namespace Plan2net\Webp\Service;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\VersionNumberUtility;

/**
 * Class Configuration
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
     * Returns the whole extension configuration or a specific property
     *
     * @param string|null $key
     * @return array|string|null
     */
    public static function get($key = null)
    {
        if (VersionNumberUtility::convertVersionStringToArray(VersionNumberUtility::getCurrentTypo3Version())['version_main'] < 9) {
            if (empty(self::$configuration) && isset($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['webp'])) {
                self::$configuration = (array)unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['webp']);
            }
        } else {
            self::$configuration = (array)GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('webp');
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
