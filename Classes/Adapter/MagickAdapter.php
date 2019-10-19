<?php
declare(strict_types=1);

namespace Plan2net\Webp\Adapter;

use TYPO3\CMS\Core\Imaging\GraphicalFunctions;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class MagickAdapter
 *
 * @package Plan2net\Webp\Adapter
 * @author Wolfgang Klinger <wk@plan2.net>
 */
class MagickAdapter implements AdapterInterface
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
    public function convert(string $originalFilePath, string $targetFilePath)
    {
        $this->getGraphicalFunctionsObject()->imageMagickExec(
            $originalFilePath,
            $targetFilePath,
            $this->parameters
        );

        if (!@is_file($targetFilePath)) {
            throw new \RuntimeException(sprintf('File %s could not be created!', $targetFilePath));
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
            // @todo remove (TYPO3 CMS 9.5)
            $graphicalFunctionsObject->init();
        }

        return $graphicalFunctionsObject;
    }
}
