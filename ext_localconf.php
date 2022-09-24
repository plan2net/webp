<?php

defined('TYPO3_MODE') || exit('Access denied');

(static function () {
    if (\Plan2net\Webp\Service\Configuration::get('hide_webp')) {
        // Hide webp files in file lists
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['fal']['defaultFilterCallbacks'][] = [
            \Plan2net\Webp\Core\Filter\FileNameFilter::class,
            'filterWebpFiles'
        ];
    }
})();
