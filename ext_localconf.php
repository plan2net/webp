<?php

defined('TYPO3_MODE') or die('Access denied');

(static function () {
    // Hide webp files in file lists
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['fal']['defaultFilterCallbacks'][] = [
        \Plan2net\Webp\Core\Filter\FileNameFilter::class,
        'filterWebpFiles'
    ];
})();
