<?php

declare(strict_types=1);

namespace Plan2net\Webp\Tests\Functional\Updates;

use PHPUnit\Framework\Attributes\Test;
use Plan2net\Webp\Updates\MigrateLegacyForcedQualityUpdate;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class MigrateLegacyForcedQualityUpdateTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = [
        'install',
        'scheduler',
    ];

    protected array $testExtensionsToLoad = [
        'plan2net/webp',
    ];

    #[Test]
    public function executeUpdateSetsForceModeOnLegacyRowsWithQuality(): void
    {
        $this->insertMetadata(1, 40, '');
        $this->insertMetadata(2, 0, '');
        $this->insertMetadata(3, 60, 'global');

        $wizard = new MigrateLegacyForcedQualityUpdate();

        self::assertTrue($wizard->updateNecessary());
        self::assertTrue($wizard->executeUpdate());
        self::assertSame('force', $this->fetchMode(1), 'legacy row with quality must become force');
        self::assertSame('', $this->fetchMode(2), 'legacy row without quality must stay untouched');
        self::assertSame('global', $this->fetchMode(3), 'explicit global choice must stay untouched');
        self::assertFalse($wizard->updateNecessary());
    }

    #[Test]
    public function updateNecessaryReturnsFalseWhenNoLegacyRowsWithQualityExist(): void
    {
        $this->insertMetadata(1, 0, '');
        $this->insertMetadata(2, 40, 'force');

        self::assertFalse((new MigrateLegacyForcedQualityUpdate())->updateNecessary());
    }

    private function insertMetadata(int $fileUid, int $quality, string $mode): void
    {
        $this->getConnectionPool()
            ->getConnectionForTable('sys_file_metadata')
            ->insert('sys_file_metadata', [
                'file' => $fileUid,
                'tx_webp_quality' => $quality,
                'tx_webp_quality_mode' => $mode,
            ]);
    }

    private function fetchMode(int $fileUid): string
    {
        return (string) $this->getConnectionPool()
            ->getConnectionForTable('sys_file_metadata')
            ->select(['tx_webp_quality_mode'], 'sys_file_metadata', ['file' => $fileUid])
            ->fetchOne();
    }
}
