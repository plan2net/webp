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

    /**
     * Checks if given mimeType is within configuration `parameters`
     *
     * @return bool
     */
    public static function isSupportedMimeType(string $mimeType): ?bool
    {
        $supportedMimeTypes = (string)self::get('mime_types');
        if (empty($supportedMimeTypes)) {
            return null;
        }

        return in_array($mimeType, explode(',', $supportedMimeTypes));
    }

    /**
     * Returns the specific command `parameters` for given mimeType
     *
     * @return string
     */
    public static function getParametersForMimeType(string $mimeType): ?string
    {
        return false === preg_match('#'.$mimeType.'::(?<param>[^\|]+)#', (string)self::get('parameters'), $match) ? null : $match['param'];
    }
}
