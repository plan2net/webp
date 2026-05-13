<?php

declare(strict_types=1);

namespace Plan2net\Webp\Tests\Functional\Fixtures\Doubles;

use Plan2net\Webp\Converter\Converter;
use Plan2net\Webp\Service\Configuration;

/**
 * Test-only Converter that writes a guaranteed-larger-than-original stub.
 *
 * Used by the functional failure-path test to deterministically trigger the
 * listener's ConvertedFileLargerThanOriginalException. Deliberate exception
 * to the "no mocking our own classes" rule — the production PhpGdConverter
 * cannot guarantee a larger-than-original output across fixture sizes and PHP
 * versions, so the failure path is otherwise unreachable.
 */
final class RecordingConverter implements Converter
{
    public function __construct(string $parameters, Configuration $configuration)
    {
    }

    public function convert(string $originalFilePath, string $targetFilePath): void
    {
        \file_put_contents($targetFilePath, \str_repeat('x', 100 * 1024));
    }
}
