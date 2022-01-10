<?php

declare(strict_types=1);

namespace Plan2net\Webp\Converter;

use TYPO3\CMS\Core\Imaging\GraphicalFunctions;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use function is_file;
use function sprintf;

/**
 * Class MagickAdapter
 * Uses the builtin TYPO3 graphical functions (imagemagick, graphicsmagick)
 *
 * @author Wolfgang Klinger <wk@plan2.net>
 */
final class MagickConverter extends AbstractConverter
{
    public function convert(string $originalFilePath, string $targetFilePath): void
    {
        $result = $this->getGraphicalFunctionsObject()->imageMagickExec(
            $originalFilePath,
            $targetFilePath,
            $this->parameters
        );

        if (!@is_file($targetFilePath)) {
            throw new \RuntimeException(sprintf('File "%s" was not created: %s!', $targetFilePath, $result ?: 'maybe missing support for webp?'));
        }
    }

    private function getGraphicalFunctionsObject(): GraphicalFunctions
    {
        static $graphicalFunctionsObject = null;

        if (null === $graphicalFunctionsObject) {
            /** @var GraphicalFunctions $graphicalFunctionsObject */
            $graphicalFunctionsObject = GeneralUtility::makeInstance(GraphicalFunctions::class);
        }

        return $graphicalFunctionsObject;
    }
}
