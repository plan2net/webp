<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

(static function (): void {
    $GLOBALS['TCA']['sys_file_metadata']['columns']['tx_webp_quality_mode'] = [
        'label' => 'LLL:EXT:webp/Resources/Private/Language/locallang.xlf:sys_file_metadata.tx_webp_quality_mode',
        'description' => 'LLL:EXT:webp/Resources/Private/Language/locallang.xlf:sys_file_metadata.tx_webp_quality_mode.description',
        'config' => [
            'type' => 'select',
            'renderType' => 'selectSingle',
            'default' => 'global',
            'items' => [
                ['label' => 'LLL:EXT:webp/Resources/Private/Language/locallang.xlf:sys_file_metadata.tx_webp_quality_mode.global', 'value' => 'global'],
                ['label' => 'LLL:EXT:webp/Resources/Private/Language/locallang.xlf:sys_file_metadata.tx_webp_quality_mode.force', 'value' => 'force'],
            ],
        ],
    ];

    ExtensionManagementUtility::addToAllTCAtypes('sys_file_metadata', 'tx_webp_quality_mode', '', 'after:description');

    $GLOBALS['TCA']['sys_file_metadata']['columns']['tx_webp_quality'] = [
        'label' => 'LLL:EXT:webp/Resources/Private/Language/locallang.xlf:sys_file_metadata.tx_webp_quality',
        'description' => 'LLL:EXT:webp/Resources/Private/Language/locallang.xlf:sys_file_metadata.tx_webp_quality.description',
        'displayCond' => [
            'OR' => [
                'FIELD:tx_webp_quality_mode:=:force',
                'AND' => [
                    'FIELD:tx_webp_quality_mode:=:',
                    'FIELD:tx_webp_quality:>:0',
                ],
            ],
        ],
        'config' => [
            'type' => 'number',
            'default' => 0,
            'range' => ['lower' => 0, 'upper' => 100],
            'size' => 5,
        ],
    ];

    ExtensionManagementUtility::addToAllTCAtypes('sys_file_metadata', 'tx_webp_quality', '', 'after:tx_webp_quality_mode');

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
