<?php

declare(strict_types=1);

namespace Plan2net\Webp\Form\Element;

use Plan2net\Webp\Backend\CompressionReportProvider;
use Plan2net\Webp\Service\Configuration;
use TYPO3\CMS\Backend\Form\Element\AbstractFormElement;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class CompressionInfoElement extends AbstractFormElement
{
    private const LL = 'LLL:EXT:webp/Resources/Private/Language/locallang.xlf:sys_file_metadata.tx_webp_compression_report';

    // Decimal-prefixed units so GeneralUtility::formatSize() renders "KB"/"MB" instead of the
    // IEC "Ki"/"Mi" default; the decimal separator is localised by formatSize() via the locale.
    private const SIZE_LABELS = ' B| KB| MB| GB| TB';

    public function render(): array
    {
        $result = $this->initializeResultArray();

        $fileUid = $this->resolveFileUid();
        $report = $fileUid > 0
            ? GeneralUtility::makeInstance(CompressionReportProvider::class)->forFile($fileUid)
            : [];

        $enabledFormats = [];
        foreach (GeneralUtility::makeInstance(Configuration::class)->getEnabledFormats() as $format) {
            $enabledFormats[] = $format->value;
        }

        $result['html'] = $this->renderTable($report, $enabledFormats);

        return $result;
    }

    private function resolveFileUid(): int
    {
        $value = $this->data['databaseRow']['file'] ?? 0;
        if (\is_array($value)) {
            $value = $value[0] ?? 0;
        }

        return (int) $value;
    }

    /**
     * @param list<array{label: string, sourceSize: int, results: array<string, array{size: int, savingsPercent: int}>}> $report
     * @param list<string>                                                                                               $enabledFormats
     */
    private function renderTable(array $report, array $enabledFormats): string
    {
        if ([] === $report) {
            return '<p class="text-muted">' . \htmlspecialchars($this->label('.empty')) . '</p>';
        }

        $missingTemplate = $this->label('.missing');

        $header = '<th>' . \htmlspecialchars($this->label('.source')) . '</th>';
        foreach ($enabledFormats as $format) {
            $header .= '<th>' . \htmlspecialchars(\strtoupper($format)) . '</th>';
        }

        $body = '';
        foreach ($report as $variant) {
            $body .= '<tr><td>' . \htmlspecialchars($variant['label']) . ' <span class="text-muted">('
                . \htmlspecialchars(GeneralUtility::formatSize($variant['sourceSize'], self::SIZE_LABELS)) . ')</span></td>';
            foreach ($enabledFormats as $format) {
                $cell = $variant['results'][$format] ?? null;
                if (null === $cell) {
                    $title = \sprintf($missingTemplate, \strtoupper($format));
                    $body .= '<td class="text-muted" title="' . \htmlspecialchars($title) . '">—</td>';
                    continue;
                }
                $savings = (int) $cell['savingsPercent'];
                $badgeClass = $savings >= 0 ? 'text-bg-success' : 'text-bg-danger';
                $savingsLabel = $savings >= 0
                    ? '&minus;' . $savings . '%'
                    : '&plus;' . (-$savings) . '%';
                $body .= '<td>' . \htmlspecialchars(GeneralUtility::formatSize($cell['size'], self::SIZE_LABELS))
                    . ' <span class="badge ' . $badgeClass . '">' . $savingsLabel . '</span></td>';
            }
            $body .= '</tr>';
        }

        return '<div class="table-fit"><table class="table table-striped table-hover">'
            . '<thead><tr>' . $header . '</tr></thead><tbody>' . $body . '</tbody></table></div>';
    }

    private function label(string $suffix): string
    {
        $language = $GLOBALS['LANG'] ?? null;
        $resolved = null !== $language ? (string) $language->sL(self::LL . $suffix) : '';

        return '' !== $resolved ? $resolved : $suffix;
    }
}
