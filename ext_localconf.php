<?php

defined('TYPO3_MODE') or die('Access denied');

(static function() {
    $signalSlotDispatcher = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\SignalSlot\Dispatcher::class);
    $signalSlotDispatcher->connect(
        \TYPO3\CMS\Core\Resource\ResourceStorage::class,
        \TYPO3\CMS\Core\Resource\Service\FileProcessingService::SIGNAL_PostFileProcess,
        \Plan2net\Webp\Processing\Webp::class,
        'processFile'
    );

    $GLOBALS['TYPO3_CONF_VARS']['SYS']['fal']['defaultFilterCallbacks'][] = [
        \Plan2net\Webp\Core\Filter\FileNameFilter::class,
        'filterWebpFiles'
    ];
})();
