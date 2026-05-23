<?php

declare(strict_types=1);

namespace Plan2net\Webp\Domain\Queue;

use Plan2net\Webp\Format\OutputFormat;

final readonly class ConversionQueueEntry
{
    public function __construct(
        public int $uid,
        public int $originalFileId,
        public int $processedFileId,
        public string $taskType,
        public string $configuration,
        public string $configurationHash,
        public int $enqueuedAt,
        public OutputFormat $format,
    ) {
    }
}
