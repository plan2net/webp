<?php
declare(strict_types=1);

namespace Plan2net\Webp\Service;

use InvalidArgumentException;
use Plan2net\Webp\Converter\Converter;
use RuntimeException;
use TYPO3\CMS\Core\Log\LogManager;
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
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function process(ProcessedFile $originalFile, ProcessedFile $processedFile)
    {
        $processedFile->setName($originalFile->getName() . '.webp');
        $processedFile->setIdentifier($originalFile->getIdentifier() . '.webp');

        $originalFilePath = $originalFile->getForLocalProcessing(false);
        // We set writable=false here even though we write to it
        // as this is already the file we want to work with
        // and don't need another copy
        $targetFilePath = $processedFile->getForLocalProcessing(false);

        $converterClass = Configuration::get('converter');
        $parameters = $this->getParametersForMimeType($originalFile->getMimeType());
        if (!empty($parameters)) {
            /** @var Converter $converter */
            $converter = GeneralUtility::makeInstance($converterClass, $parameters);
            $converter->convert($originalFilePath, $targetFilePath);
            $fileSizeTargetFile = @filesize($targetFilePath);
            if ($originalFile->getSize() <= $fileSizeTargetFile) {
                $logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
                $logger->warning(
                    sprintf('Processed file (%s) is larger than the original (%s)!',
                        $targetFilePath, $originalFilePath)
                );
            }
            $processedFile->updateProperties(
                [
                    'width' => $originalFile->getProperty('width'),
                    'height' => $originalFile->getProperty('height'),
                    'size' => $fileSizeTargetFile
                ]
            );
        } else {
            throw new InvalidArgumentException(sprintf('No options given for adapter "%s"!', $converterClass));
        }
    }

    /**
     * @param $mimeType
     * @return bool
     */
    public static function isSupportedMimeType(string $mimeType): bool
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
