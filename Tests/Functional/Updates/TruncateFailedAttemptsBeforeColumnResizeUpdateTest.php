<?php

declare(strict_types=1);

namespace Plan2net\Webp\Tests\Functional\Updates;

use PHPUnit\Framework\Attributes\Test;
use Plan2net\Webp\Updates\TruncateFailedAttemptsBeforeColumnResizeUpdate;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class TruncateFailedAttemptsBeforeColumnResizeUpdateTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = [
        'install',
    ];

    protected array $testExtensionsToLoad = [
        'plan2net/webp',
    ];

    #[Test]
    public function executeUpdateRemovesAllRowsWhenLongHashesArePresent(): void
    {
        $this->insertFailedAttempt(1, \str_repeat('a', 40));
        $this->insertFailedAttempt(2, \str_repeat('b', 32));

        $wizard = new TruncateFailedAttemptsBeforeColumnResizeUpdate();

        self::assertTrue($wizard->updateNecessary());
        self::assertTrue($wizard->executeUpdate());
        self::assertSame(0, $this->countFailedRows());
        self::assertFalse($wizard->updateNecessary());
    }

    #[Test]
    public function updateNecessaryReturnsFalseWhenOnlyShortHashesArePresent(): void
    {
        $this->insertFailedAttempt(1, \str_repeat('a', 32));

        self::assertFalse((new TruncateFailedAttemptsBeforeColumnResizeUpdate())->updateNecessary());
    }

    #[Test]
    public function updateNecessaryReturnsFalseForEmptyTable(): void
    {
        self::assertFalse((new TruncateFailedAttemptsBeforeColumnResizeUpdate())->updateNecessary());
    }

    private function insertFailedAttempt(int $fileId, string $configurationHash): void
    {
        $this->getConnectionPool()
            ->getConnectionForTable('tx_webp_failed')
            ->insert('tx_webp_failed', [
                'file_id' => $fileId,
                'configuration' => '',
                'configuration_hash' => $configurationHash,
            ]);
    }

    private function countFailedRows(): int
    {
        return (int) $this->getConnectionPool()
            ->getConnectionForTable('tx_webp_failed')
            ->count('uid', 'tx_webp_failed', []);
    }
}
