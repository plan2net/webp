<?php

declare(strict_types=1);

namespace Plan2net\Webp\Tests\Functional\Fixtures\Doubles;

use Plan2net\Webp\Converter\AbstractConverter;
use Plan2net\Webp\Format\OutputFormat;
use Plan2net\Webp\Service\Configuration;

/**
 * Test-only Converter that records the parameter string it was constructed with
 * (so a test can assert the effective, override-applied parameters) and emits a
 * fixed, valid WebP.
 */
final class CapturingConverter extends AbstractConverter
{
    /** @var list<string> */
    public static array $receivedParameters = [];

    public function __construct(string $parameters, Configuration $configuration)
    {
        self::$receivedParameters[] = $parameters;
        parent::__construct($parameters, $configuration);
    }

    public function convertTo(string $originalFilePath, string $targetFilePath, OutputFormat $format): void
    {
        \copy(__DIR__ . '/../Images/tiny.webp', $targetFilePath);
    }
}
