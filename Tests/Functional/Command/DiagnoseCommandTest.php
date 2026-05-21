<?php

declare(strict_types=1);

namespace Plan2net\Webp\Tests\Functional\Command;

use PHPUnit\Framework\Attributes\Test;
use Plan2net\Webp\Command\DiagnoseCommand;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class DiagnoseCommandTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = [
        'install',
        'scheduler',
    ];

    protected array $testExtensionsToLoad = [
        'plan2net/webp',
    ];

    #[Test]
    public function commandRunsWithoutFatalError(): void
    {
        $tester = new CommandTester($this->get(DiagnoseCommand::class));
        $exitCode = $tester->execute([]);

        self::assertContains($exitCode, [0, 1], 'webp:diagnose must complete with exit 0 or 1, not fatal');
        self::assertNotEmpty($tester->getDisplay(), 'webp:diagnose must produce output');
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Default LocalConfiguration ships one storage; findAll() materializes it before the command queries.
        $this->get(StorageRepository::class)->findAll();
    }
}
