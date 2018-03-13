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

    /**
     * Perform image conversion
     *
     * @param \TYPO3\CMS\Core\Resource\ProcessedFile $originalFile
     * @param \TYPO3\CMS\Core\Resource\ProcessedFile $processedFile
     */
    public function process($originalFile, &$processedFile)
    {
        $extensionConfiguration = (array)unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['webp']);
        $processedFile->setName($originalFile->getName() . '.webp');
        $processedFile->setIdentifier($originalFile->getIdentifier() . '.webp');
        if (!empty($extensionConfiguration['magick_parameters'])) {
            $parameters = $extensionConfiguration['magick_parameters'];
        } else {
            $parameters = '-quality=85 -define webp:lossless=false';
        }
        $originalFilePath = $originalFile->getForLocalProcessing(false);
        $processedFilePath = $processedFile->getForLocalProcessing(false);
        $this->getGraphicalFunctionsObject()->imageMagickExec($originalFilePath,
            $processedFilePath, $parameters);
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

}
