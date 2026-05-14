<?php

declare(strict_types=1);

namespace Plan2net\Webp\Task;

use TYPO3\CMS\Scheduler\AbstractAdditionalFieldProvider;
use TYPO3\CMS\Scheduler\Controller\SchedulerModuleController;
use TYPO3\CMS\Scheduler\Task\AbstractTask;

final class ProcessWebpQueueTaskAdditionalFieldProvider extends AbstractAdditionalFieldProvider
{
    private const FIELD_NAME = 'webp_batchSize';

    public function getAdditionalFields(array &$taskInfo, $task, SchedulerModuleController $schedulerModule): array
    {
        $currentValue = $task instanceof ProcessWebpQueueTask ? $task->batchSize : ProcessWebpQueueTask::DEFAULT_BATCH_SIZE;
        $taskInfo[self::FIELD_NAME] = $currentValue;

        return [
            self::FIELD_NAME => [
                'code' => \sprintf(
                    '<input type="number" min="1" name="tx_scheduler[%s]" value="%d" class="form-control" />',
                    self::FIELD_NAME,
                    $currentValue
                ),
                'label' => 'LLL:EXT:webp/Resources/Private/Language/locallang.xlf:task.processQueue.batchSize.label',
                'cshKey' => '',
                'cshLabel' => '',
            ],
        ];
    }

    public function validateAdditionalFields(array &$submittedData, SchedulerModuleController $schedulerModule): bool
    {
        return (int) ($submittedData[self::FIELD_NAME] ?? 0) >= 1;
    }

    public function saveAdditionalFields(array $submittedData, AbstractTask $task): void
    {
        if ($task instanceof ProcessWebpQueueTask) {
            $task->batchSize = \max(1, (int) ($submittedData[self::FIELD_NAME] ?? ProcessWebpQueueTask::DEFAULT_BATCH_SIZE));
        }
    }
}
