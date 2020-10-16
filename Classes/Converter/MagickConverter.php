<?php
declare(strict_types=1);

namespace Plan2net\Webp\Converter;

use TYPO3\CMS\Core\Imaging\GraphicalFunctions;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class MagickAdapter
 * Uses the builtin TYPO3 graphical functions (imagemagick, graphicsmagick)
 *
 * @package Plan2net\Webp\Converter
 * @author Wolfgang Klinger <wk@plan2.net>
 */
class MagickConverter implements Converter
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
        if (!is_file($originalFilePath)) {
            return;
        }

        $result = $this->getGraphicalFunctionsObject()->imageMagickExec(
            $originalFilePath,
            $targetFilePath,
            $this->parameters
        );

        if (!@is_file($targetFilePath)) {
            throw new \RuntimeException(sprintf('File "%s" was not created: %s!',
                $targetFilePath,
                $result ?: 'maybe missing support for webp?'
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
}
