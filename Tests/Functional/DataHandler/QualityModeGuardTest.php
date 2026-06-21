<?php

declare(strict_types=1);

namespace Plan2net\Webp\Tests\Functional\DataHandler;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class QualityModeGuardTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = [
        'install',
        'scheduler',
    ];

    protected array $testExtensionsToLoad = [
        'plan2net/webp',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Database/be_users.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Database/sys_file.csv');
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);

        $fileadminPath = $this->instancePath . '/fileadmin/';
        if (!is_dir($fileadminPath)) {
            mkdir($fileadminPath, 0o777, true);
        }
        copy(__DIR__ . '/../Fixtures/Images/tiny.png', $fileadminPath . 'tiny.png');
    }

    #[Test]
    public function savingForceModeWithoutQualityFallsBackToGlobal(): void
    {
        $metadataUid = $this->insertMetadata(1, 0, 'global');

        $this->processDatamap($metadataUid, ['tx_webp_quality_mode' => 'force']);

        self::assertSame('global', $this->fetchMode($metadataUid));
    }

    #[Test]
    public function savingForceModeWithQualityIsPersisted(): void
    {
        $metadataUid = $this->insertMetadata(1, 0, 'global');

        $this->processDatamap($metadataUid, ['tx_webp_quality_mode' => 'force', 'tx_webp_quality' => 50]);

        self::assertSame('force', $this->fetchMode($metadataUid));
    }

    #[Test]
    public function savingAnUnrelatedFieldKeepsAnActiveForceMode(): void
    {
        $metadataUid = $this->insertMetadata(1, 50, 'force');

        $this->processDatamap($metadataUid, ['alternative' => 'a description']);

        self::assertSame('force', $this->fetchMode($metadataUid));
    }

    private function insertMetadata(int $fileUid, int $quality, string $mode): int
    {
        $connection = $this->getConnectionPool()->getConnectionForTable('sys_file_metadata');
        $connection->insert('sys_file_metadata', [
            'file' => $fileUid,
            'tx_webp_quality' => $quality,
            'tx_webp_quality_mode' => $mode,
        ]);

        return (int) $connection->lastInsertId();
    }

    private function processDatamap(int $metadataUid, array $fields): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(['sys_file_metadata' => [$metadataUid => $fields]], []);
        $dataHandler->process_datamap();
    }

    private function fetchMode(int $metadataUid): string
    {
        return (string) $this->getConnectionPool()
            ->getConnectionForTable('sys_file_metadata')
            ->select(['tx_webp_quality_mode'], 'sys_file_metadata', ['uid' => $metadataUid])
            ->fetchOne();
    }
}
