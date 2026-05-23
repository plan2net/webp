<?php

declare(strict_types=1);

namespace Plan2net\Webp\Domain\Queue;

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
    ) {
    }
}
