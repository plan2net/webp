<?php

declare(strict_types=1);

namespace Plan2net\Webp\Tests\Functional\Fixtures\Doubles;

use Plan2net\Webp\Converter\Converter;

/**
 * Test-only Converter that writes a guaranteed-larger-than-original stub.
 *
 * Used by test 8 (recordsFailedAttemptWhenConvertedFileLargerThanOriginal) to
 * deterministically trigger the listener's ConvertedFileLargerThanOriginalException
 * path. Deliberate exception to the "no mocking our own classes" rule — the
 * production PhpGdConverter cannot guarantee a larger-than-original output across
 * fixture sizes and PHP versions, so the failure path is otherwise unreachable.
 */
final class RecordingConverter implements Converter
{
    // Required by GeneralUtility::makeInstance($class, $parameters) at the call site; ignored by this stub.
    public function __construct(private readonly string $parameters)
    {
    }

    public function convert(string $originalFilePath, string $targetFilePath): void
    {
        file_put_contents($targetFilePath, str_repeat('x', 100 * 1024));
    }
}
