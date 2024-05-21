<?php

namespace Plan2net\Webp\Service;

class CreateWebp
{
    public function __construct(
        readonly string $processedFileIdentifier,
        readonly string $processedFileWebpIdentifier,
        readonly string $taskType,
        readonly array $configuration
    ) {
    }
}
