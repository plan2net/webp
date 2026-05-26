<?php

declare(strict_types=1);

namespace Plan2net\Webp\Service;

use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\ProcessedFileRepository;
use TYPO3\CMS\Core\Resource\Processing\TaskTypeRegistry;

final readonly class ProcessedFileWriter
{
    public function __construct(
        private ProcessedFileRepository $repository,
        private TaskTypeRegistry $taskTypeRegistry,
        private Typo3Version $typo3Version,
    ) {
    }

    public function add(ProcessedFile $processedFile, string $taskType, array $configuration): void
    {
        if ($this->typo3Version->getMajorVersion() >= 14) {
            $task = $this->taskTypeRegistry->getTaskForType($taskType, $processedFile, $configuration);
            $this->repository->add($processedFile, $task);

            return;
        }

        $this->repository->add($processedFile);
    }
}
