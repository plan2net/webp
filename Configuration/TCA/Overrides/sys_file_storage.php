<?php

declare(strict_types=1);

use Plan2net\Webp\Service\StorageSiblingMode;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

(static function (): void {
    $GLOBALS['TCA']['sys_file_storage']['columns']['tx_webp_mode'] = [
        'label' => 'LLL:EXT:webp/Resources/Private/Language/locallang.xlf:sys_file_storage.tx_webp_mode',
        'description' => 'LLL:EXT:webp/Resources/Private/Language/locallang.xlf:sys_file_storage.tx_webp_mode.description',
        'config' => [
            'type' => 'radio',
            'default' => StorageSiblingMode::Auto->value,
            'items' => [
                ['label' => 'LLL:EXT:webp/Resources/Private/Language/locallang.xlf:sys_file_storage.tx_webp_mode.auto',     'value' => StorageSiblingMode::Auto->value],
                ['label' => 'LLL:EXT:webp/Resources/Private/Language/locallang.xlf:sys_file_storage.tx_webp_mode.enabled',  'value' => StorageSiblingMode::Enabled->value],
                ['label' => 'LLL:EXT:webp/Resources/Private/Language/locallang.xlf:sys_file_storage.tx_webp_mode.disabled', 'value' => StorageSiblingMode::Disabled->value],
            ],
        ],
    ];

    ExtensionManagementUtility::addToAllTCAtypes(
        'sys_file_storage',
        'tx_webp_mode',
        '',
        'after:configuration'
    );
})();
