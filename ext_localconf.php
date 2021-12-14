<?php defined('TYPO3_MODE') || die();

(static function () {
    // Hide webp files in file lists
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['fal']['defaultFilterCallbacks'][] = [
        \Plan2net\Webp\Core\Filter\FileNameFilter::class,
        'filterWebpFiles'
    ];
})();
