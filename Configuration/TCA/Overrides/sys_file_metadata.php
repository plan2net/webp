<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

(static function (): void {
    $GLOBALS['TCA']['sys_file_metadata']['columns']['tx_webp_quality'] = [
        'label' => 'LLL:EXT:webp/Resources/Private/Language/locallang.xlf:sys_file_metadata.tx_webp_quality',
        'description' => 'LLL:EXT:webp/Resources/Private/Language/locallang.xlf:sys_file_metadata.tx_webp_quality.description',
        'config' => [
            'type' => 'number',
            'default' => 0,
            'range' => ['lower' => 0, 'upper' => 100],
            'size' => 5,
        ],
    ];

    ExtensionManagementUtility::addToAllTCAtypes(
        'sys_file_metadata',
        'tx_webp_quality',
        '',
        'after:description'
    );

    $GLOBALS['TCA']['sys_file_metadata']['columns']['tx_webp_compression_report'] = [
        'label' => 'LLL:EXT:webp/Resources/Private/Language/locallang.xlf:sys_file_metadata.tx_webp_compression_report',
        'config' => [
            'type' => 'user',
            'renderType' => 'webpCompressionInfo',
        ],
    ];

    ExtensionManagementUtility::addToAllTCAtypes(
        'sys_file_metadata',
        'tx_webp_compression_report',
        '',
        'after:tx_webp_quality'
    );
})();
