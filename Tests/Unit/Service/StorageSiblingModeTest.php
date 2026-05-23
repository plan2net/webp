<?php

declare(strict_types=1);

namespace Plan2net\Webp\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Plan2net\Webp\Service\StorageSiblingMode;
use TYPO3\CMS\Core\Resource\ResourceStorage;

final class StorageSiblingModeTest extends TestCase
{
    #[Test]
    public function nullStorageIsDisabled(): void
    {
        self::assertFalse(StorageSiblingMode::isEnabledFor(null));
    }

    #[Test]
    public function fallbackStorageWithUidZeroIsDisabled(): void
    {
        $storage = $this->createStorage(uid: 0, writable: true, driver: 'Local', mode: StorageSiblingMode::Enabled);

        self::assertFalse(StorageSiblingMode::isEnabledFor($storage));
    }

    #[Test]
    public function readOnlyStorageIsDisabledRegardlessOfMode(): void
    {
        $storage = $this->createStorage(uid: 1, writable: false, driver: 'Local', mode: StorageSiblingMode::Enabled);

        self::assertFalse(StorageSiblingMode::isEnabledFor($storage));
    }

    #[Test]
    #[DataProvider('modeMatrix')]
    public function resolvesAccordingToModeAndDriver(string $driver, StorageSiblingMode $mode, bool $expected): void
    {
        $storage = $this->createStorage(uid: 1, writable: true, driver: $driver, mode: $mode);

        self::assertSame($expected, StorageSiblingMode::isEnabledFor($storage));
    }

    public static function modeMatrix(): array
    {
        return [
            'Local + Auto' => ['Local',      StorageSiblingMode::Auto,     true],
            'Local + Enabled' => ['Local',      StorageSiblingMode::Enabled,  true],
            'Local + Disabled' => ['Local',      StorageSiblingMode::Disabled, false],
            'Remote + Auto' => ['FakeRemote', StorageSiblingMode::Auto,     false],
            'Remote + Enabled' => ['FakeRemote', StorageSiblingMode::Enabled,  true],
            'Remote + Disabled' => ['FakeRemote', StorageSiblingMode::Disabled, false],
        ];
    }

    private function createStorage(int $uid, bool $writable, string $driver, StorageSiblingMode $mode): ResourceStorage
    {
        $storage = $this->createMock(ResourceStorage::class);
        $storage->method('getUid')->willReturn($uid);
        $storage->method('isWritable')->willReturn($writable);
        $storage->method('getDriverType')->willReturn($driver);
        $storage->method('getStorageRecord')->willReturn(['uid' => $uid, 'tx_webp_mode' => $mode->value]);

        return $storage;
    }
}
