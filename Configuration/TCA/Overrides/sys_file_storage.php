<?php

declare(strict_types=1);

use Plan2net\Webp\Service\StorageWebpMode;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

(static function (): void {
    $GLOBALS['TCA']['sys_file_storage']['columns']['tx_webp_mode'] = [
        'label' => 'LLL:EXT:webp/Resources/Private/Language/locallang.xlf:sys_file_storage.tx_webp_mode',
        'description' => 'LLL:EXT:webp/Resources/Private/Language/locallang.xlf:sys_file_storage.tx_webp_mode.description',
        'config' => [
            'type' => 'radio',
            'default' => StorageWebpMode::Auto->value,
            'items' => [
                ['label' => 'LLL:EXT:webp/Resources/Private/Language/locallang.xlf:sys_file_storage.tx_webp_mode.auto',     'value' => StorageWebpMode::Auto->value],
                ['label' => 'LLL:EXT:webp/Resources/Private/Language/locallang.xlf:sys_file_storage.tx_webp_mode.enabled',  'value' => StorageWebpMode::Enabled->value],
                ['label' => 'LLL:EXT:webp/Resources/Private/Language/locallang.xlf:sys_file_storage.tx_webp_mode.disabled', 'value' => StorageWebpMode::Disabled->value],
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
