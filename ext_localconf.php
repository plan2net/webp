<?php

use Plan2net\Webp\Core\Filter\FileNameFilter;
use Plan2net\Webp\Service\Configuration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

(static function () {
    if (GeneralUtility::makeInstance(Configuration::class)->isHideWebp()) {
        // Hide webp files in file lists
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['fal']['defaultFilterCallbacks'][] = [
            FileNameFilter::class,
            'filterWebpFiles',
        ];
    }

    // Register the scheduler task only when cms-scheduler is installed.
    // Both cms-scheduler and cms-install are soft dependencies of this extension;
    // the task wrapper class and the upgrade wizard load lazily so missing
    // packages don't fatal at boot.
    if (\class_exists(TYPO3\CMS\Scheduler\Task\AbstractTask::class)) {
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][Plan2net\Webp\Task\ProcessWebpQueueTask::class] = [
            'extension' => 'webp',
            'title' => 'LLL:EXT:webp/Resources/Private/Language/locallang.xlf:task.processQueue.title',
            'description' => 'LLL:EXT:webp/Resources/Private/Language/locallang.xlf:task.processQueue.description',
            'icon' => 'mimetypes-x-tx_scheduler_task_group',
            'additionalFields' => Plan2net\Webp\Task\ProcessWebpQueueTaskAdditionalFieldProvider::class,
        ];
    }
})();
