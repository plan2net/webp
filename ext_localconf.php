<?php

defined('TYPO3_MODE') or die('Access denied');

$signalSlotDispatcher->connect(
    \TYPO3\CMS\Core\Resource\ResourceStorage::class,
    \TYPO3\CMS\Core\Resource\Service\FileProcessingService::SIGNAL_PostFileProcess,
    \Plan2net\Webp\Processing\Webp::class,
    'processFile'
);

