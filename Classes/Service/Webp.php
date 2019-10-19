<?php
declare(strict_types=1);

namespace Plan2net\Webp\Service;

use Plan2net\Webp\Adapter\AdapterInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class Webp
 *
 * @package Plan2net\Webp\Service
 * @author  Wolfgang Klinger <wk@plan2.net>
 */
class Webp
{
    /**
     * Perform image conversion
     *
     * @param \TYPO3\CMS\Core\Resource\ProcessedFile $originalFile
     * @param \TYPO3\CMS\Core\Resource\ProcessedFile $processedFile
     */
    public function process($originalFile, $processedFile)
    {
        $processedFile->setName($originalFile->getName() . '.webp');
        $processedFile->setIdentifier($originalFile->getIdentifier() . '.webp');

        $originalFilePath = $originalFile->getForLocalProcessing(false);
        $targetFilePath = $processedFile->getForLocalProcessing(false);

        $adapterClass = Configuration::get('adapter');
        $parameters = Configuration::get('parameters');
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
    }
}
