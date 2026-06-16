<?php

declare(strict_types=1);

namespace Plan2net\Webp\Command;

use Plan2net\Webp\Format\OutputFormat;
use Plan2net\Webp\Format\SourceMimeType;
use Plan2net\Webp\Service\Configuration;
use Plan2net\Webp\Webserver\RewriteConfigGenerator;
use Plan2net\Webp\Webserver\WebserverType;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Attribute\AsNonSchedulableCommand;

#[AsCommand(
    name: 'webp:webserver-config',
    description: 'Print the webserver content-negotiation snippet for the configured formats.'
)]
#[AsNonSchedulableCommand]
final class GenerateWebserverConfigCommand extends Command
{
    // Fixed delivery priority — first match wins in every server.
    private const PRIORITY = [OutputFormat::Avif, OutputFormat::Webp, OutputFormat::Jxl];

    private const PLACEMENT_HEADERS = [
        'http' => '# Place in the http {} block:',
        'server' => '# Place in the server {} block:',
        'main' => '# Paste into your server configuration:',
    ];

    public function __construct(
        private readonly Configuration $configuration,
        private readonly RewriteConfigGenerator $generator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('server', null, InputOption::VALUE_REQUIRED, 'Target webserver: nginx, apache or caddy');
        $this->addOption('scope', null, InputOption::VALUE_REQUIRED, 'Emit only one section raw (server-specific key, e.g. http/server/main)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $server = WebserverType::tryFrom((string) $input->getOption('server'));
        if (null === $server) {
            $output->writeln('<error>--server must be one of: nginx, apache, caddy</error>');

            return Command::INVALID;
        }

        $formats = $this->configuredFormatsInPriorityOrder();
        if ([] === $formats) {
            $output->writeln('<error>No output format is configured to produce (check converter + parameters and formats_enabled).</error>');

            return Command::FAILURE;
        }

        $sourceExtensions = $this->sourceExtensionsFor($formats);
        if ([] === $sourceExtensions) {
            $output->writeln('<error>No source mime types are configured (mime_types), so no rule can match. Configure mime_types first.</error>');

            return Command::FAILURE;
        }

        $sections = $this->generator->generate($server, $formats, $sourceExtensions);

        $scope = $input->getOption('scope');
        if (null !== $scope) {
            if (!isset($sections[$scope])) {
                $output->writeln(\sprintf('<error>--scope for %s must be one of: %s</error>', $server->value, \implode(', ', $server->scopeKeys())));

                return Command::INVALID;
            }
            $output->write($sections[(string) $scope]);

            return Command::SUCCESS;
        }

        foreach ($sections as $key => $fragment) {
            $output->writeln(self::PLACEMENT_HEADERS[$key] ?? \sprintf('# %s:', $key));
            $output->writeln($fragment);
        }

        return Command::SUCCESS;
    }

    /**
     * @return list<OutputFormat>
     */
    private function configuredFormatsInPriorityOrder(): array
    {
        $enabled = $this->configuration->getEnabledFormats();
        $ordered = [];
        foreach (self::PRIORITY as $format) {
            if (\in_array($format, $enabled, true) && $this->configuration->isFormatRunnable($format)) {
                $ordered[] = $format;
            }
        }

        return $ordered;
    }

    /**
     * @param list<OutputFormat> $formats
     *
     * @return list<string>
     */
    private function sourceExtensionsFor(array $formats): array
    {
        $mimeTypes = [];
        foreach (SourceMimeType::all() as $mimeType) {
            foreach ($formats as $format) {
                if ($this->configuration->isSupportedMimeTypeFor($format, $mimeType)) {
                    $mimeTypes[] = $mimeType;
                    break;
                }
            }
        }

        return SourceMimeType::extensionsAllowedBy($mimeTypes);
    }
}
