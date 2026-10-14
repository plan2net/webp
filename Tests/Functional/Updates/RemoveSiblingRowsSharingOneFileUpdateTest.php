<?php

declare(strict_types=1);

namespace Plan2net\Webp\Tests\Functional\Updates;

use PHPUnit\Framework\Attributes\Test;
use Plan2net\Webp\Updates\RemoveSiblingRowsSharingOneFileUpdate;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class RemoveSiblingRowsSharingOneFileUpdateTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = [
        'install',
        'scheduler',
    ];

    protected array $testExtensionsToLoad = [
        'plan2net/webp',
    ];

    #[Test]
    public function removesSiblingRowsThatShareOneFile(): void
    {
        $this->insertRow('/_processed_/a/1/csm_photo.png.webp');
        $this->insertRow('/_processed_/a/1/csm_photo.png.webp');
        $this->insertRow('/_processed_/a/1/csm_other.png.webp');
        $this->insertRow('');
        $this->insertRow('');

        $wizard = new RemoveSiblingRowsSharingOneFileUpdate();

        self::assertTrue($wizard->updateNecessary());
        self::assertTrue($wizard->executeUpdate());
        self::assertSame(['', '', '/_processed_/a/1/csm_other.png.webp'], $this->identifiers());
        self::assertFalse($wizard->updateNecessary());
    }

    #[Test]
    public function nothingToDoWhenEverySiblingHasItsOwnFile(): void
    {
        $this->insertRow('/_processed_/a/1/csm_photo.png.webp');
        $this->insertRow('/_processed_/a/1/csm_photo.png.avif');

        self::assertFalse((new RemoveSiblingRowsSharingOneFileUpdate())->updateNecessary());
    }

    private function insertRow(string $identifier): void
    {
        $this->getConnectionPool()
            ->getConnectionForTable('sys_file_processedfile')
            ->insert('sys_file_processedfile', [
                'storage' => 1,
                'original' => 1,
                'identifier' => $identifier,
                'name' => \basename($identifier),
                'configuration' => '',
                'task_type' => 'Image.CropScaleMask',
            ]);
    }

    /**
     * @return list<string>
     */
    private function identifiers(): array
    {
        $identifiers = $this->getConnectionPool()
            ->getConnectionForTable('sys_file_processedfile')
            ->select(['identifier'], 'sys_file_processedfile')
            ->fetchFirstColumn();
        \sort($identifiers);

        return $identifiers;
    }
}
