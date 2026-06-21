<?php

declare(strict_types=1);

namespace Plan2net\Webp\DataHandler;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class QualityModeGuard
{
    public function processDatamap_postProcessFieldArray(string $status, string $table, string|int $id, array &$fieldArray): void
    {
        if ('sys_file_metadata' !== $table) {
            return;
        }

        $existingRecord = 'update' === $status
            ? (BackendUtility::getRecord($table, (int) $id, 'tx_webp_quality_mode, tx_webp_quality') ?? [])
            : [];

        $mode = (string) ($fieldArray['tx_webp_quality_mode'] ?? $existingRecord['tx_webp_quality_mode'] ?? '');
        if ('force' !== $mode) {
            return;
        }

        $quality = (int) ($fieldArray['tx_webp_quality'] ?? $existingRecord['tx_webp_quality'] ?? 0);
        if ($quality >= 1 && $quality <= 100) {
            return;
        }

        $fieldArray['tx_webp_quality_mode'] = 'global';

        $flashMessage = GeneralUtility::makeInstance(
            FlashMessage::class,
            'Forcing a quality requires a compression quality between 1 and 100.',
            'webp: Quality mode reset to "Use global setting"',
            ContextualFeedbackSeverity::WARNING,
            true,
        );
        GeneralUtility::makeInstance(FlashMessageService::class)
            ->getMessageQueueByIdentifier()
            ->enqueue($flashMessage);
    }
}
