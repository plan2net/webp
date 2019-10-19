<?php
declare(strict_types=1);

namespace Plan2net\Webp\Service;

use TYPO3\CMS\Core\SingletonInterface;

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
     * Returns the whole extension configuration or a specific property
     *
     * @param string|null $key
     * @return array|string|null
     */
    public static function get($key = null)
    {
        if (empty(self::$configuration) && isset($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['webp'])) {
            self::$configuration = (array)unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['webp'], [false]);
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
