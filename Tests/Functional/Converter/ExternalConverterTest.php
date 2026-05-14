<?php

declare(strict_types=1);

namespace Plan2net\Webp\Tests\Functional\Converter;

use PHPUnit\Framework\Attributes\Test;
use Plan2net\Webp\Converter\ExternalConverter;
use Plan2net\Webp\Service\Configuration;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class ExternalConverterTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = [
        'install',
    ];

    protected array $testExtensionsToLoad = [
        'plan2net/webp',
    ];

    private string $workingDirectory;
    private string $fakeBinary;
    private string|false $previousLocale;

    #[Test]
    public function convertPreservesNonAsciiBytesInPathsWhenLocaleIsC(): void
    {
        $sourceFile = $this->workingDirectory . '/Größe_Übung_42.png';
        $targetFile = $sourceFile . '.webp';
        file_put_contents($sourceFile, 'fake-png-payload');

        $extensionConfiguration = $this->createMock(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->with('webp')->willReturn(['silent' => '1']);
        $configuration = new Configuration($extensionConfiguration);

        $converter = new ExternalConverter($this->fakeBinary . ' %s %s', $configuration);
        $converter->convert($sourceFile, $targetFile);

        self::assertFileExists($targetFile, 'webp sibling must be created at the umlaut target path');
        self::assertSame('fake-png-payload', file_get_contents($targetFile));
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Forces escapeshellarg() into its non-UTF-8 codepath, where it strips
        // multibyte characters. Without this, the host locale (e.g. C.UTF-8)
        // would hide the bug reported in #89.
        $this->previousLocale = setlocale(LC_CTYPE, '0');
        setlocale(LC_CTYPE, 'C');

        $this->workingDirectory = sys_get_temp_dir() . '/webp-issue-89-' . bin2hex(random_bytes(4));
        mkdir($this->workingDirectory, 0o755, true);

        $this->fakeBinary = $this->workingDirectory . '/fake-cwebp';
        file_put_contents($this->fakeBinary, "#!/bin/sh\ncp \"\$1\" \"\$2\"\n");
        chmod($this->fakeBinary, 0o755);
    }

    protected function tearDown(): void
    {
        if (false !== $this->previousLocale) {
            setlocale(LC_CTYPE, $this->previousLocale);
        }
        $this->removeDirectory($this->workingDirectory);

        parent::tearDown();
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        foreach (scandir($path) as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }
            $full = $path . '/' . $entry;
            is_dir($full) ? $this->removeDirectory($full) : @unlink($full);
        }
        @rmdir($path);
    }
}
