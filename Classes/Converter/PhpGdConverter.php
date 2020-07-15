<?php
declare(strict_types=1);

namespace Plan2net\Webp\Converter;

use TYPO3\CMS\Core\Imaging\GraphicalFunctions;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class PhpGdConverter
 * Uses the php gd library to generate webp images
 *
 * @package Plan2net\Webp\Converter
 * @author André Schließer <a.schliesser@zeroseven.de>
 */
class PhpGdConverter implements Converter
{
    /**
     * @var
     */
    protected $parameters;

    /**
     * @param string $parameters
     */
    public function __construct(string $parameters)
    {
        $this->parameters = $parameters;
    }

    /**
     * @inheritdoc
     */
    public function convert(string $originalFilePath, string $targetFilePath): void
    {
        if (!$this->isRunnable()) {
            return;
        }

        // Get quality
        preg_match('/quality(\s|=)(\d{1,3})/', $this->parameters, $matches);
        $quality = ((int)$matches[2] > 0) ? (int)$matches[2] : 80;

        // Get image object from file path
        $image = $this->getGraphicalFunctionsObject()->imageCreateFromFile($originalFilePath);

        ob_start();
        imagewebp($image, null, $quality);
        if (ob_get_length() % 2 === 1) {
            throw new \RuntimeException(sprintf('File "%s" was not created: %s!',
                $targetFilePath,
                'PHP GD not installed?'
            ));
        }
        $result = ob_get_clean();
        GeneralUtility::writeFile($targetFilePath, $result, true);

        if (!@is_file($targetFilePath)) {
            throw new \RuntimeException(sprintf('File "%s" was not created: %s!',
                $targetFilePath,
                $result ?: 'PHP GD: Could not write file!'
            ));
        }
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
        }

        return $graphicalFunctionsObject;
    }

    /**
     * @return bool
     */
    public function isRunnable(): bool
    {
        return function_exists('imagewebp')
            && defined('IMG_WEBP')
            && (imagetypes() & IMG_WEBP) === IMG_WEBP;
    }
}
