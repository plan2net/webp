<?php

declare(strict_types=1);

namespace Plan2net\Webp\Tests\Functional\Service;

use PHPUnit\Framework\Attributes\Test;
use Plan2net\Webp\Service\Configuration;
use Plan2net\Webp\Service\SettledVariants;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class SettledVariantsTest extends FunctionalTestCase
{
    private const VARIANT_IDENTIFIER = '/_processed_/a/1/csm_tiny_a1.png';

    protected array $coreExtensionsToLoad = [
        'install',
        'scheduler',
    ];

    protected array $testExtensionsToLoad = [
        'plan2net/webp',
    ];

    #[Test]
    public function anAddedVariantIsSettled(): void
    {
        $settledVariants = $this->get(SettledVariants::class);
        $file = $this->file(1, 'sha-a');

        $settledVariants->add($file, $this->variant(7));

        self::assertTrue($settledVariants->contains($file, $this->variant(7)));
        self::assertFalse($settledVariants->contains($file, $this->variant(8)), 'other variants of the file stay unsettled');
        self::assertFalse($settledVariants->contains($this->file(2, 'sha-a'), $this->variant(7)), 'other files stay unsettled');
    }

    #[Test]
    public function settledVariantsAreRememberedAcrossRequests(): void
    {
        $file = $this->file(1, 'sha-a');
        $this->get(SettledVariants::class)->add($file, $this->variant(7));

        self::assertTrue($this->freshInstance()->contains($file, $this->variant(7)));
    }

    #[Test]
    public function aVariantRegeneratedUnderANewNameIsNoLongerSettled(): void
    {
        $settledVariants = $this->get(SettledVariants::class);
        $file = $this->file(1, 'sha-a');
        $settledVariants->add($file, $this->variant(7));

        self::assertFalse($settledVariants->contains($file, $this->variant(7, '/_processed_/a/1/csm_tiny_b2.png')));
    }

    #[Test]
    public function aChangedOriginalIsNoLongerSettled(): void
    {
        $settledVariants = $this->get(SettledVariants::class);
        $settledVariants->add($this->file(1, 'sha-a'), $this->variant(7));

        self::assertFalse($settledVariants->contains($this->file(1, 'sha-b'), $this->variant(7)));
        self::assertFalse($this->freshInstance()->contains($this->file(1, 'sha-b'), $this->variant(7)));
    }

    #[Test]
    public function aChangedForcedQualityIsNoLongerSettled(): void
    {
        $settledVariants = $this->get(SettledVariants::class);
        $settledVariants->add($this->file(1, 'sha-a', 50), $this->variant(7));

        self::assertFalse($settledVariants->contains($this->file(1, 'sha-a', 60), $this->variant(7)));
    }

    #[Test]
    public function aConfigurationChangeIsNoLongerSettled(): void
    {
        $settledVariants = $this->get(SettledVariants::class);
        $file = $this->file(1, 'sha-a');
        $settledVariants->add($file, $this->variant(7));

        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['webp']['parameters'] = 'image/png::-quality 60';

        self::assertFalse($settledVariants->contains($file, $this->variant(7)));
    }

    #[Test]
    public function aVariantWithoutRowIsNeverSettled(): void
    {
        $settledVariants = $this->get(SettledVariants::class);
        $file = $this->file(1, 'sha-a');

        $settledVariants->add($file, $this->variant(0));

        self::assertFalse($settledVariants->contains($file, $this->variant(0)));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['webp'] = [
            'parameters' => 'image/png::-quality 75',
        ];
    }

    private function freshInstance(): SettledVariants
    {
        return new SettledVariants(
            $this->get(CacheManager::class)->getCache('webp_siblings'),
            $this->get(Configuration::class),
        );
    }

    private function file(int $uid, string $sha1, ?int $forcedQuality = null): File
    {
        $file = $this->createMock(File::class);
        $file->method('getUid')->willReturn($uid);
        $file->method('getSha1')->willReturn($sha1);
        $file->method('getProperty')->willReturnMap([
            ['tx_webp_quality_mode', null === $forcedQuality ? '' : 'force'],
            ['tx_webp_quality', $forcedQuality],
        ]);

        return $file;
    }

    private function variant(int $uid, string $identifier = self::VARIANT_IDENTIFIER): ProcessedFile
    {
        $variant = $this->createMock(ProcessedFile::class);
        $variant->method('getUid')->willReturn($uid);
        $variant->method('getIdentifier')->willReturn($identifier);

        return $variant;
    }
}
