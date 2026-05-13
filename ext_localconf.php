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
})();
