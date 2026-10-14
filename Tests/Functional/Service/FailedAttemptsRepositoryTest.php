<?php

declare(strict_types=1);

namespace Plan2net\Webp\Tests\Functional\Service;

use PHPUnit\Framework\Attributes\Test;
use Plan2net\Webp\Format\OutputFormat;
use Plan2net\Webp\Service\FailedAttemptsRepository;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class FailedAttemptsRepositoryTest extends FunctionalTestCase
{
    private const VARIANT = '/_processed_/a/1/csm_tiny_a1.png';

    protected array $coreExtensionsToLoad = [
        'install',
        'scheduler',
    ];

    protected array $testExtensionsToLoad = [
        'plan2net/webp',
    ];

    #[Test]
    public function recordAndWasAttemptedRoundtripPerFormat(): void
    {
        $repository = $this->get(FailedAttemptsRepository::class);

        $repository->record(1, self::VARIANT, 'image/png::Q=75', OutputFormat::Webp);

        self::assertTrue($repository->wasAttempted(1, self::VARIANT, 'image/png::Q=75', OutputFormat::Webp));
    }

    #[Test]
    public function failedAttemptForOneFormatDoesNotBlockAnother(): void
    {
        $repository = $this->get(FailedAttemptsRepository::class);

        $repository->record(1, self::VARIANT, 'image/png::Q=75', OutputFormat::Avif);

        self::assertTrue($repository->wasAttempted(1, self::VARIANT, 'image/png::Q=75', OutputFormat::Avif), 'AVIF attempt is recorded');
        self::assertFalse($repository->wasAttempted(1, self::VARIANT, 'image/png::Q=75', OutputFormat::Webp), 'WebP attempt must NOT be blocked by an AVIF failure');
        self::assertFalse($repository->wasAttempted(1, self::VARIANT, 'image/png::Q=75', OutputFormat::Jxl), 'JXL attempt must NOT be blocked by an AVIF failure');
    }

    #[Test]
    public function differentParametersAreTrackedSeparatelyWithinTheSameFormat(): void
    {
        $repository = $this->get(FailedAttemptsRepository::class);

        $repository->record(1, self::VARIANT, 'image/png::Q=75', OutputFormat::Webp);

        self::assertTrue($repository->wasAttempted(1, self::VARIANT, 'image/png::Q=75', OutputFormat::Webp));
        self::assertFalse($repository->wasAttempted(1, self::VARIANT, 'image/png::Q=99', OutputFormat::Webp), 'A parameter change resets the cache');
    }

    #[Test]
    public function differentFilesAreTrackedSeparately(): void
    {
        $repository = $this->get(FailedAttemptsRepository::class);

        $repository->record(1, self::VARIANT, 'image/png::Q=75', OutputFormat::Webp);

        self::assertTrue($repository->wasAttempted(1, self::VARIANT, 'image/png::Q=75', OutputFormat::Webp));
        self::assertFalse($repository->wasAttempted(2, self::VARIANT, 'image/png::Q=75', OutputFormat::Webp));
    }

    #[Test]
    public function differentVariantsOfOneFileAreTrackedSeparately(): void
    {
        $repository = $this->get(FailedAttemptsRepository::class);

        $repository->record(1, self::VARIANT, 'image/png::Q=75', OutputFormat::Webp);

        self::assertTrue($repository->wasAttempted(1, self::VARIANT, 'image/png::Q=75', OutputFormat::Webp));
        self::assertFalse($repository->wasAttempted(1, '/_processed_/b/2/csm_tiny_b2.png', 'image/png::Q=75', OutputFormat::Webp));
    }
}
