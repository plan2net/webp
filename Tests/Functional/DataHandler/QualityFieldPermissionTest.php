<?php

declare(strict_types=1);

namespace Plan2net\Webp\Tests\Functional\DataHandler;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class QualityFieldPermissionTest extends FunctionalTestCase
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

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Database/be_users_editor.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Database/sys_file.csv');
    }

    #[Test]
    public function editorWithoutTheGrantCannotChangeTheQuality(): void
    {
        $metadataUid = $this->insertMetadata(1, 0, 'global');
        $this->logInAs(2);

        $this->processDatamap($metadataUid, ['tx_webp_quality_mode' => 'force', 'tx_webp_quality' => 50]);

        self::assertSame(0, $this->fetchQuality($metadataUid));
    }

    #[Test]
    public function editorWithoutTheGrantCannotChangeTheQualityMode(): void
    {
        $metadataUid = $this->insertMetadata(1, 50, 'global');
        $this->logInAs(2);

        $this->processDatamap($metadataUid, ['tx_webp_quality_mode' => 'force']);

        self::assertSame('global', $this->fetchMode($metadataUid));
    }

    #[Test]
    public function editorWithTheGrantCanChangeTheQuality(): void
    {
        $metadataUid = $this->insertMetadata(1, 0, 'global');
        $this->logInAs(3);

        $this->processDatamap($metadataUid, ['tx_webp_quality_mode' => 'force', 'tx_webp_quality' => 50]);

        self::assertSame(50, $this->fetchQuality($metadataUid));
        self::assertSame('force', $this->fetchMode($metadataUid));
    }

    private function logInAs(int $userUid): void
    {
        $backendUser = $this->setUpBackendUser($userUid);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
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

    private function fetchQuality(int $metadataUid): int
    {
        return (int) $this->getConnectionPool()
            ->getConnectionForTable('sys_file_metadata')
            ->select(['tx_webp_quality'], 'sys_file_metadata', ['uid' => $metadataUid])
            ->fetchOne();
    }

    private function fetchMode(int $metadataUid): string
    {
        return (string) $this->getConnectionPool()
            ->getConnectionForTable('sys_file_metadata')
            ->select(['tx_webp_quality_mode'], 'sys_file_metadata', ['uid' => $metadataUid])
            ->fetchOne();
    }
}
