<?php
declare(strict_types=1);

namespace Plan2net\Webp\Service\Image;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Imaging\GraphicalFunctions;

/**
 * Class Webp
 *
 * @package Plan2net\Webp\Service\Image
 * @author  Wolfgang Klinger <wk@plan2.net>
 */
class Webp
{

    const MAGICK_DEFAULT_OPTIONS = '-quality=95 -define webp:lossless=false';

    /**
     * Perform image conversion
     *
     * @param \TYPO3\CMS\Core\Resource\ProcessedFile $originalFile
     * @param \TYPO3\CMS\Core\Resource\ProcessedFile $processedFile
     */
    public function process($originalFile, &$processedFile)
    {
        $processedFile->setName($originalFile->getName() . '.webp');
        $processedFile->setIdentifier($originalFile->getIdentifier() . '.webp');

        $originalFilePath = $originalFile->getForLocalProcessing(false);
        $processedFilePath = $processedFile->getForLocalProcessing(false);

        // create WebP file
        $this->getGraphicalFunctionsObject()->imageMagickExec(
            $originalFilePath,
            $processedFilePath,
            $this->getMagickParameters()
        );
        $processedFile->updateProperties(
            [
                'width' => $originalFile->getProperty('width'),
                'height' => $originalFile->getProperty('height'),
                'size' => @filesize($processedFilePath)
            ]
        );
    }

    /**
     * @return GraphicalFunctions
     */
    protected function getGraphicalFunctionsObject(): GraphicalFunctions
    {
        static $graphicalFunctionsObject = null;

        if ($graphicalFunctionsObject === null) {
            /** @var GraphicalFunctions $graphicalFunctionsObject */
            $graphicalFunctionsObject = GeneralUtility::makeInstance(GraphicalFunctions::class);
            $graphicalFunctionsObject->init();
        }

        return $graphicalFunctionsObject;
    }

    /**
     * @return string
     */
    protected function getMagickParameters(): string
    {
        $extensionConfiguration = (array)unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['webp']);
        if (!empty($extensionConfiguration['magick_parameters'])) {
            $parameters = $extensionConfiguration['magick_parameters'];
        } else {
            $parameters = self::MAGICK_DEFAULT_OPTIONS;
        }

        return $parameters;
    }

}
