<?php

declare(strict_types=1);

namespace Plan2net\Webp\Tests\Functional\Form;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Backend\Form\FormDataCompiler;
use TYPO3\CMS\Backend\Form\FormDataGroup\TcaDatabaseRecord;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class QualityFieldVisibilityTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = [
        'install',
        'scheduler',
    ];

    protected array $testExtensionsToLoad = [
        'plan2net/webp',
    ];

    private ServerRequest $request;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Database/be_users.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Database/sys_file.csv');
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
        $this->request = (new ServerRequest())
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
        $GLOBALS['TYPO3_REQUEST'] = $this->request;
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_REQUEST']);

        parent::tearDown();
    }

    #[Test]
    public function qualityFieldIsEditableWhileModeIsGlobal(): void
    {
        $metadataUid = $this->insertMetadata(1, 0, 'global');

        $columns = $this->compileFormColumns($metadataUid);

        self::assertArrayHasKey('tx_webp_quality', $columns);
    }

    #[Test]
    public function qualityFieldIsEditableWhileModeIsForce(): void
    {
        $metadataUid = $this->insertMetadata(1, 50, 'force');

        $columns = $this->compileFormColumns($metadataUid);

        self::assertArrayHasKey('tx_webp_quality', $columns);
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

    private function compileFormColumns(int $metadataUid): array
    {
        $formData = GeneralUtility::makeInstance(FormDataCompiler::class)->compile(
            [
                'command' => 'edit',
                'tableName' => 'sys_file_metadata',
                'vanillaUid' => $metadataUid,
                'request' => $this->request,
            ],
            GeneralUtility::makeInstance(TcaDatabaseRecord::class),
        );

        return $formData['processedTca']['columns'];
    }
}
