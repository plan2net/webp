<?php

declare(strict_types=1);

namespace Plan2net\Webp\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Plan2net\Webp\Service\StorageWebpMode;
use TYPO3\CMS\Core\Resource\ResourceStorage;

final class StorageWebpModeTest extends TestCase
{
    #[Test]
    public function nullStorageIsDisabled(): void
    {
        self::assertFalse(StorageWebpMode::isEnabledFor(null));
    }

    #[Test]
    public function fallbackStorageWithUidZeroIsDisabled(): void
    {
        $storage = $this->createStorage(uid: 0, writable: true, driver: 'Local', mode: StorageWebpMode::Enabled);

        self::assertFalse(StorageWebpMode::isEnabledFor($storage));
    }

    #[Test]
    public function readOnlyStorageIsDisabledRegardlessOfMode(): void
    {
        $storage = $this->createStorage(uid: 1, writable: false, driver: 'Local', mode: StorageWebpMode::Enabled);

        self::assertFalse(StorageWebpMode::isEnabledFor($storage));
    }

    #[Test]
    #[DataProvider('modeMatrix')]
    public function resolvesAccordingToModeAndDriver(string $driver, StorageWebpMode $mode, bool $expected): void
    {
        $storage = $this->createStorage(uid: 1, writable: true, driver: $driver, mode: $mode);

        self::assertSame($expected, StorageWebpMode::isEnabledFor($storage));
    }

    public static function modeMatrix(): array
    {
        return [
            'Local + Auto' => ['Local',      StorageWebpMode::Auto,     true],
            'Local + Enabled' => ['Local',      StorageWebpMode::Enabled,  true],
            'Local + Disabled' => ['Local',      StorageWebpMode::Disabled, false],
            'Remote + Auto' => ['FakeRemote', StorageWebpMode::Auto,     false],
            'Remote + Enabled' => ['FakeRemote', StorageWebpMode::Enabled,  true],
            'Remote + Disabled' => ['FakeRemote', StorageWebpMode::Disabled, false],
        ];
    }

    private function createStorage(int $uid, bool $writable, string $driver, StorageWebpMode $mode): ResourceStorage
    {
        $storage = $this->createMock(ResourceStorage::class);
        $storage->method('getUid')->willReturn($uid);
        $storage->method('isWritable')->willReturn($writable);
        $storage->method('getDriverType')->willReturn($driver);
        $storage->method('getStorageRecord')->willReturn(['uid' => $uid, 'tx_webp_mode' => $mode->value]);

        return $storage;
    }
}
