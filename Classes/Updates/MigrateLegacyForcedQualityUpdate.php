<?php

declare(strict_types=1);

namespace Plan2net\Webp\Updates;

use TYPO3\CMS\Core\Attribute\UpgradeWizard;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

#[UpgradeWizard('webp.migrateLegacyForcedQuality')]
final class MigrateLegacyForcedQualityUpdate implements UpgradeWizardInterface
{
    private const TABLE = 'sys_file_metadata';

    public function getTitle(): string
    {
        return 'webp: Migrate per-file quality overrides to the force mode';
    }

    public function getDescription(): string
    {
        return 'Before the quality mode field existed, any per-file compression quality between 1 and 100 acted as a forced quality. This wizard sets the quality mode of those rows to "force" so they keep behaving the same; without it, re-saving such a file\'s metadata in the backend would silently reset the mode to "global".';
    }

    public function executeUpdate(): bool
    {
        $connection = $this->connection();
        $connection->executeStatement(
            'UPDATE ' . $connection->quoteIdentifier(self::TABLE)
            . " SET tx_webp_quality_mode = 'force'"
            . " WHERE tx_webp_quality_mode = '' AND tx_webp_quality BETWEEN 1 AND 100"
        );

        return true;
    }

    public function updateNecessary(): bool
    {
        $connection = $this->connection();
        $legacyRowsWithQuality = (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM ' . $connection->quoteIdentifier(self::TABLE)
            . " WHERE tx_webp_quality_mode = '' AND tx_webp_quality BETWEEN 1 AND 100"
        );

        return $legacyRowsWithQuality > 0;
    }

    public function getPrerequisites(): array
    {
        return [];
    }

    private function connection(): \TYPO3\CMS\Core\Database\Connection
    {
        return GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable(self::TABLE);
    }
}
