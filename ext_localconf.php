<?php

use Plan2net\Webp\Core\Filter\FileNameFilter;
use Plan2net\Webp\Format\OutputFormat;
use Plan2net\Webp\Service\Configuration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

(static function () {
    // TYPO3 v12 omits `webp` from SYS/mediafile_ext; v13+ added it. AVIF and
    // JXL are not in any TYPO3 default — register them so FAL's source-folder
    // publish path (Folder::addFile) accepts the new sibling extensions.
    $mediaFileExtensions = (string) ($GLOBALS['TYPO3_CONF_VARS']['SYS']['mediafile_ext'] ?? '');
    $known = GeneralUtility::trimExplode(',', $mediaFileExtensions, true);
    foreach (OutputFormat::cases() as $format) {
        if (!\in_array($format->value, $known, true)) {
            $known[] = $format->value;
        }
    }
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['mediafile_ext'] = \implode(',', $known);

    if (GeneralUtility::makeInstance(Configuration::class)->isHideSiblings()) {
        // Hide generated sibling files (.webp/.avif/.jxl) in BE file lists
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['fal']['defaultFilterCallbacks'][] = [
            FileNameFilter::class,
            'filterSiblingFiles',
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

    // Read-only FormEngine widget showing per-format compression results
    // (sys_file_metadata). Registered globally; stable API across v12–v14.
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1718200000] = [
        'nodeName' => 'webpCompressionInfo',
        'priority' => 40,
        'class' => Plan2net\Webp\Form\Element\CompressionInfoElement::class,
    ];
})();
