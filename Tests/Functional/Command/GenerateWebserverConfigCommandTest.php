<?php

declare(strict_types=1);

namespace Plan2net\Webp\Tests\Functional\Command;

use PHPUnit\Framework\Attributes\Test;
use Plan2net\Webp\Command\GenerateWebserverConfigCommand;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class GenerateWebserverConfigCommandTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['install', 'scheduler'];
    protected array $testExtensionsToLoad = ['plan2net/webp'];

    private function applyConfig(array $overrides): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['webp'] = $overrides + [
            'converter' => 'Plan2net\\Webp\\Converter\\MagickConverter',
            'parameters' => 'image/jpeg::-quality 85',
            'mime_types' => 'image/jpeg,image/png,image/gif',
            'formats_enabled' => 'webp',
        ];
    }

    private function executeCommand(array $input): CommandTester
    {
        $tester = new CommandTester($this->get(GenerateWebserverConfigCommand::class));
        $tester->execute($input);

        return $tester;
    }

    #[Test]
    public function fullOutputAddsPlacementHeadersAroundBothNginxScopes(): void
    {
        $this->applyConfig([]);

        $tester = $this->executeCommand(['--server' => 'nginx']);

        self::assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        self::assertStringContainsString('# Place in the http {} block:', $output);
        self::assertStringContainsString('map $http_accept $sibling_suffix {', $output);
        self::assertStringContainsString('# Place in the server {} block:', $output);
        self::assertStringContainsString('location ~* ^.+\\.(jpg|jpeg|png|gif)$ {', $output);
    }

    #[Test]
    public function formatsAreOrderedAvifWebpJxlRegardlessOfConfigOrder(): void
    {
        $this->applyConfig([
            'formats_enabled' => 'jxl,webp,avif',
            'converter_avif' => 'Plan2net\\Webp\\Converter\\MagickConverter',
            'parameters_avif' => 'image/jpeg::-quality 60',
            'converter_jxl' => 'Plan2net\\Webp\\Converter\\MagickConverter',
            'parameters_jxl' => 'image/jpeg::-quality 75',
        ]);

        $output = $this->executeCommand(['--server' => 'apache'])->getDisplay();

        self::assertLessThan(\strpos($output, 'T=image/webp'), \strpos($output, 'T=image/avif'));
        self::assertLessThan(\strpos($output, 'T=image/jxl'), \strpos($output, 'T=image/webp'));
    }

    #[Test]
    public function scopeEmitsOnlyThatSectionRawWithoutPlacementHeader(): void
    {
        $this->applyConfig([]);

        $output = $this->executeCommand(['--server' => 'nginx', '--scope' => 'http'])->getDisplay();

        self::assertStringContainsString('map $http_accept $sibling_suffix {', $output);
        self::assertStringNotContainsString('location ~*', $output);
        self::assertStringNotContainsString('Place in the', $output);
    }

    #[Test]
    public function unknownServerFails(): void
    {
        $this->applyConfig([]);
        self::assertNotSame(0, $this->executeCommand(['--server' => 'lighttpd'])->getStatusCode());
    }

    #[Test]
    public function invalidScopeForServerFails(): void
    {
        $this->applyConfig([]);
        self::assertNotSame(0, $this->executeCommand(['--server' => 'apache', '--scope' => 'http'])->getStatusCode());
    }

    #[Test]
    public function noConfiguredFormatsFails(): void
    {
        $this->applyConfig(['formats_enabled' => 'webp', 'converter' => '', 'parameters' => '']);
        self::assertNotSame(0, $this->executeCommand(['--server' => 'nginx'])->getStatusCode());
    }

    #[Test]
    public function noSourceExtensionsFails(): void
    {
        $this->applyConfig(['mime_types' => '']);
        self::assertNotSame(0, $this->executeCommand(['--server' => 'nginx'])->getStatusCode());
    }
}
