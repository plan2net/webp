<?php

declare(strict_types=1);

namespace Plan2net\Webp\Updates;

use TYPO3\CMS\Core\Attribute\UpgradeWizard;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

#[UpgradeWizard('webp.truncateFailedAttemptsBeforeColumnResize')]
final class TruncateFailedAttemptsBeforeColumnResizeUpdate implements UpgradeWizardInterface
{
    private const TABLE = 'tx_webp_failed';

    public function getTitle(): string
    {
        return 'webp: Truncate tx_webp_failed before configuration_hash column resize';
    }

    public function getDescription(): string
    {
        return 'Older versions of plan2net/webp shipped tx_webp_failed.configuration_hash as VARCHAR(40). The current schema is VARCHAR(32), and TYPO3\'s database analyzer refuses to shrink the column while rows hold values longer than 32 characters. This wizard empties the cache of failed conversion attempts so the analyzer can complete the migration; the cache is rebuilt on demand.';
    }

    public function executeUpdate(): bool
    {
        $connection = $this->connection();
        $connection->executeStatement('DELETE FROM ' . $connection->quoteIdentifier(self::TABLE));

        return true;
    }

    public function updateNecessary(): bool
    {
        $connection = $this->connection();
        if (!$connection->createSchemaManager()->tablesExist([self::TABLE])) {
            return false;
        }
        $rowsWithLongHash = (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM ' . $connection->quoteIdentifier(self::TABLE)
            . ' WHERE LENGTH(' . $connection->quoteIdentifier('configuration_hash') . ') > 32'
        );

        return $rowsWithLongHash > 0;
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
