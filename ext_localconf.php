<?php

use Plan2net\Webp\Core\Filter\FileNameFilter;
use Plan2net\Webp\Service\Configuration;

defined('TYPO3') || exit;

(static function () {
    if (Configuration::get('hide_webp')) {
        // Hide webp files in file lists
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['fal']['defaultFilterCallbacks'][] = [
            FileNameFilter::class,
            'filterWebpFiles',
        ];
    }
})();
