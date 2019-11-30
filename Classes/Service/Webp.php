<?php
declare(strict_types=1);

namespace Plan2net\Webp\Service;

use Exception;
use Plan2net\Webp\Adapter\AdapterInterface;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class Webp
 *
 * @package Plan2net\Webp\Service
 * @author  Wolfgang Klinger <wk@plan2.net>
 */
class Webp
{
    public const SUPPORTED_MIME_TYPES = [
        'image/jpeg',
        'image/png'
    ];

    /**
     * Perform image conversion
     *
     * @param ProcessedFile $originalFile
     * @param ProcessedFile $processedFile
     */
    public function process($originalFile, $processedFile)
    {
        $processedFile->setName($originalFile->getName() . '.webp');
        $processedFile->setIdentifier($originalFile->getIdentifier() . '.webp');

        $originalFilePath = $originalFile->getForLocalProcessing(false);
        // We set writable=false here even though we write to it
        // as this is already the file we want to work with
        // and don't need another copy
        $targetFilePath = $processedFile->getForLocalProcessing(false);

        $adapterClass = Configuration::get('adapter');
        $parameters = $this->getParametersForMimeType($originalFile->getMimeType());
        if ($parameters) {
            try {
                /** @var AdapterInterface $adapter */
                $adapter = GeneralUtility::makeInstance($adapterClass, $parameters);
                $adapter->convert(
                    $originalFilePath,
                    $targetFilePath
                );
                $processedFile->updateProperties(
                    [
                        'width' => $originalFile->getProperty('width'),
                        'height' => $originalFile->getProperty('height'),
                        'size' => @filesize($targetFilePath)
                    ]
                );
            } catch (Exception $e) {
                $processedFile->delete();
                // @todo log
            }
        } else {
            $processedFile->delete();
            // @todo log
        }
    }

    /**
     * @param $mimeType
     * @return bool
     */
    public static function isSupportedMimeType($mimeType): bool
    {
        return in_array(strtolower($mimeType), self::SUPPORTED_MIME_TYPES, true);
    }

    /**
     * @param string $mimeType
     * @return string|null
     */
    protected function getParametersForMimeType(string $mimeType): ?string
    {
        $parameters = explode('|', Configuration::get('parameters'));
        foreach ($parameters as $parameter) {
            [$type, $options] = explode('::', $parameter, 2);
            // Fallback to old options format
            if (empty($options)) {
                return $type;
            }
            if ($type === $mimeType) {
                return $options;
            }
        }

        return null;
    }
}
