<?php

declare(strict_types=1);

namespace Plan2net\Webp\Command;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use Plan2net\Webp\Converter\Converter;
use Plan2net\Webp\Converter\ExternalConverter;
use Plan2net\Webp\Converter\MagickConverter;
use Plan2net\Webp\Converter\PhpGdConverter;
use Plan2net\Webp\Converter\VipsConverter;
use Plan2net\Webp\Service\Configuration;
use Plan2net\Webp\Service\StorageWebpMode;
use Plan2net\Webp\Service\Webp as WebpService;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Attribute\AsNonSchedulableCommand;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\ProcessedFileRepository;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Site\SiteFinder;

#[AsCommand(
    name: 'webp:diagnose',
    description: 'End-to-end health check: storages, converter, async pipeline, failed attempts, and optional HTTP delivery probe.',
)]
#[AsNonSchedulableCommand]
final class DiagnoseCommand extends Command
{
    private int $failureCount = 0;
    private int $warningCount = 0;
    private ?string $firstFailure = null;

    public function __construct(
        private readonly StorageRepository $storageRepository,
        private readonly Configuration $configuration,
        private readonly WebpService $webpService,
        private readonly RequestFactory $requestFactory,
        private readonly SiteFinder $siteFinder,
        private readonly ConnectionPool $connectionPool,
        private readonly ResourceFactory $resourceFactory,
        private readonly ProcessedFileRepository $processedFileRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('url', null, InputOption::VALUE_REQUIRED, 'Override the auto-detected base URL for the HTTP delivery probe.');
        $this->addOption('file', null, InputOption::VALUE_REQUIRED, 'sys_file UID for per-file deep dive.');
        $this->addOption('insecure', null, InputOption::VALUE_NONE, 'Disable TLS certificate verification on the HTTP probe (for self-signed dev certs).');
        $this->addOption('probe-timeout', null, InputOption::VALUE_REQUIRED, 'HTTP probe timeout in seconds.', '10');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->writeHeader($io, 'webp:diagnose', 'options=bold,reverse');

        if ($this->isInstallationEmpty()) {
            $io->error('No storages configured. Create at least one sys_file_storage entry before running diagnose.');

            return Command::FAILURE;
        }

        $this->reportStorages($io);
        $this->reportConverter($io);
        $this->reportAsyncPipeline($io);
        $this->reportFailedAttempts($io);

        $probeUrl = $this->resolveProbeBaseUrl($input, $io);
        if (null !== $probeUrl) {
            $this->reportDeliveryProbe($io, $probeUrl, $input);
        }

        $fileUid = $input->getOption('file');
        if (\is_string($fileUid) && '' !== $fileUid) {
            $this->reportFileDeepDive($io, (int) $fileUid);
        }

        $this->emitRecommendation($io);

        return $this->failureCount > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function isInstallationEmpty(): bool
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_file_storage');
        $count = (int) $queryBuilder
            ->count('uid')
            ->from('sys_file_storage')
            ->where($queryBuilder->expr()->gt('uid', 0))
            ->executeQuery()
            ->fetchOne();

        return 0 === $count;
    }

    private function reportStorages(SymfonyStyle $io): void
    {
        $this->writeHeader($io, 'Storages');

        try {
            $storages = $this->storageRepository->findAll();
        } catch (\Exception $exception) {
            $this->writeStatus($io, '✗', \sprintf('StorageRepository::findAll() failed: %s', $exception->getMessage()));
            ++$this->failureCount;
            $this->captureFirstFailure(\sprintf("StorageRepository::findAll() threw %s: %s\nThis usually means a sys_file_storage row has invalid configuration. Inspect the table directly to identify the broken row.", $exception::class, $exception->getMessage()));

            return;
        }

        $visibleUids = [];
        foreach ($storages as $storage) {
            if (0 === $storage->getUid()) {
                continue;
            }
            $visibleUids[] = $storage->getUid();
            if (!$storage->isOnline()) {
                $io->writeln(\sprintf('· #%d %s — offline, skipping', $storage->getUid(), $storage->getName()));
                continue;
            }

            $mode = StorageWebpMode::tryFrom(
                (int) ($storage->getStorageRecord()['tx_webp_mode'] ?? StorageWebpMode::Auto->value),
            ) ?? StorageWebpMode::Auto;
            $isEnabled = StorageWebpMode::isEnabledFor($storage);
            $siblingCount = $this->countSiblings($storage->getUid());

            $marker = match (true) {
                $isEnabled => '✓',
                StorageWebpMode::Disabled === $mode => '·',
                default => '!',
            };
            $this->writeStatus($io, $marker, \sprintf(
                '#%d ⋅ %s ⋅ driver=%s ⋅ mode=%s ⋅ %s ⋅ %s',
                $storage->getUid(),
                $storage->getName(),
                $storage->getDriverType(),
                $mode->name,
                $storage->isWritable() ? 'writable' : 'read-only',
                $this->pluralize($siblingCount, '.webp file sibling'),
            ));

            if (!$isEnabled && StorageWebpMode::Disabled !== $mode) {
                $reason = $this->silentOffReason($storage, $mode);
                $io->writeln(\sprintf('  <fg=yellow>↳ %s</>', $reason));
                ++$this->warningCount;
                $this->captureFirstFailure(\sprintf(
                    "Storage #%d (%s) is silently off:\n  %s\n  Set tx_webp_mode = 1 (Enabled) on this storage if you want .webp generation here.",
                    $storage->getUid(),
                    $storage->getName(),
                    $reason,
                ));
            }
        }

        $this->reportPhantomStorages($io, $visibleUids);
    }

    /**
     * @param list<int> $visibleUids
     */
    private function reportPhantomStorages(SymfonyStyle $io, array $visibleUids): void
    {
        $query = $this->connectionPool->getQueryBuilderForTable('sys_file_storage');
        $rows = $query
            ->select('uid', 'name', 'driver', 'is_online')
            ->from('sys_file_storage')
            ->where($query->expr()->gt('uid', 0))
            ->executeQuery()
            ->fetchAllAssociative();

        foreach ($rows as $row) {
            $uid = (int) $row['uid'];
            if (\in_array($uid, $visibleUids, true)) {
                continue;
            }
            $isOnline = (bool) $row['is_online'];
            if (!$isOnline) {
                $io->writeln(\sprintf('· #%d ⋅ %s ⋅ driver=%s ⋅ offline, skipping', $uid, $row['name'], $row['driver']));
                continue;
            }

            $this->writeStatus($io, '!', \sprintf(
                '#%d ⋅ %s ⋅ driver=%s ⋅ row exists in sys_file_storage but TYPO3 cannot instantiate it',
                $uid,
                $row['name'],
                $row['driver'],
            ));
            $io->writeln(\sprintf('  <fg=yellow>↳ driver \'%s\' is not registered with the FAL DriverRegistry</>', $row['driver']));
            ++$this->warningCount;
            $this->captureFirstFailure(\sprintf(
                "Storage #%d (%s) driver '%s' is not registered.\nInstall the extension that provides this FAL driver, or fix the driver column on this sys_file_storage row.",
                $uid,
                $row['name'],
                $row['driver'],
            ));
        }
    }

    private function countSiblings(int $storageUid): int
    {
        $fileQuery = $this->connectionPool->getQueryBuilderForTable('sys_file');
        $directCount = (int) $fileQuery
            ->count('uid')
            ->from('sys_file')
            ->where(
                $fileQuery->expr()->eq('storage', $fileQuery->createNamedParameter($storageUid, Connection::PARAM_INT)),
                $fileQuery->expr()->eq('extension', $fileQuery->createNamedParameter('webp')),
            )
            ->executeQuery()
            ->fetchOne();

        $processedQuery = $this->connectionPool->getQueryBuilderForTable('sys_file_processedfile');
        $processedCount = (int) $processedQuery
            ->count('uid')
            ->from('sys_file_processedfile')
            ->where(
                $processedQuery->expr()->eq('storage', $processedQuery->createNamedParameter($storageUid, Connection::PARAM_INT)),
                $processedQuery->expr()->like('identifier', $processedQuery->createNamedParameter('%.webp')),
            )
            ->executeQuery()
            ->fetchOne();

        return $directCount + $processedCount;
    }

    private function silentOffReason(ResourceStorage $storage, StorageWebpMode $mode): string
    {
        if (!$storage->isWritable()) {
            return 'storage is read-only — extension cannot write .webp siblings';
        }
        if (StorageWebpMode::Auto === $mode && 'Local' !== $storage->getDriverType()) {
            return \sprintf('mode=Auto and driver=%s — Auto is on only for Local drivers. Switch to Enabled to opt in.', $storage->getDriverType());
        }

        return 'StorageWebpMode::isEnabledFor returned false (storage uid 0 or other gate)';
    }

    private function reportConverter(SymfonyStyle $io): void
    {
        $this->writeHeader($io, 'Converter');

        $converterClass = $this->configuration->getConverter();
        $parameters = $this->configuration->getParameters();

        if ('' === $converterClass) {
            $this->writeStatus($io, '✗', 'no converter configured — set EXTENSIONS/webp/converter in TYPO3 settings');
            ++$this->failureCount;
            $this->captureFirstFailure('No converter configured. Edit the TYPO3 extension settings for webp and pick PhpGdConverter, MagickConverter, or ExternalConverter.');

            return;
        }

        $io->writeln(\sprintf('· class:      %s', $converterClass));
        $io->writeln(\sprintf('· parameters: %s', '' === $parameters ? '(empty)' : $parameters));

        $verdict = match (true) {
            PhpGdConverter::class === $converterClass => $this->checkPhpGdConverter(),
            MagickConverter::class === $converterClass => $this->checkMagickConverter(),
            ExternalConverter::class === $converterClass => $this->checkExternalConverter($parameters),
            VipsConverter::class === $converterClass => $this->checkVipsConverter(),
            default => $this->checkCustomConverter($converterClass),
        };

        $this->writeStatus($io, $verdict['marker'], $verdict['line']);
        if ('✗' === $verdict['marker']) {
            ++$this->failureCount;
            $this->captureFirstFailure($verdict['recommendation']);
        } elseif ('!' === $verdict['marker']) {
            ++$this->warningCount;
            if ('' !== $verdict['recommendation']) {
                $this->captureFirstFailure($verdict['recommendation']);
            }
        }

        $this->checkParameterParsing($io);
    }

    /**
     * @return array{marker: string, line: string, recommendation: string}
     */
    private function checkPhpGdConverter(): array
    {
        if (!\function_exists('imagewebp')) {
            return [
                'marker' => '✗',
                'line' => 'PHP GD does not expose imagewebp() — install php-gd compiled with webp support',
                'recommendation' => "PHP's GD extension lacks WebP support. Install a GD build compiled with --with-webp (Debian/Ubuntu: php-gd already supports it on 8.2+; on Alpine you need libwebp-dev at build time).",
            ];
        }
        if (0 === (\imagetypes() & IMG_WEBP)) {
            return [
                'marker' => '✗',
                'line' => 'imagewebp() exists but IMG_WEBP flag is missing — GD was built without WebP support',
                'recommendation' => 'GD reports no IMG_WEBP support at runtime. Rebuild PHP-GD with libwebp linked in, or switch to MagickConverter / ExternalConverter.',
            ];
        }

        return [
            'marker' => '✓',
            'line' => 'PHP GD with WebP support available',
            'recommendation' => '',
        ];
    }

    /**
     * @return array{marker: string, line: string, recommendation: string}
     */
    private function checkMagickConverter(): array
    {
        $processorPath = (string) ($GLOBALS['TYPO3_CONF_VARS']['GFX']['processor_path'] ?? '');
        if ('' !== $processorPath && !\str_ends_with($processorPath, \DIRECTORY_SEPARATOR)) {
            $processorPath .= \DIRECTORY_SEPARATOR;
        }
        $location = '' === $processorPath ? 'on PATH' : 'at ' . $processorPath;

        $timedOutTool = null;
        foreach (['convert' => [$processorPath . 'convert', '-version'], 'gm' => [$processorPath . 'gm', 'version']] as $tool => $command) {
            $result = self::runCommandWithTimeout($command, 5);
            if ($result['timedOut']) {
                $timedOutTool = $tool;
                continue;
            }
            if (0 === $result['exitCode'] && \str_contains($result['output'], 'WebP')) {
                return [
                    'marker' => '✓',
                    'line' => \sprintf('%s with WebP delegate found %s', 'convert' === $tool ? 'ImageMagick' : 'GraphicsMagick', $location),
                    'recommendation' => '',
                ];
            }
        }

        if (null !== $timedOutTool) {
            return [
                'marker' => '✗',
                'line' => \sprintf('%s did not respond within 5 seconds %s', 'convert' === $timedOutTool ? 'ImageMagick' : 'GraphicsMagick', $location),
                'recommendation' => \sprintf('`%s -version` did not return within 5 seconds. Investigate why the binary is slow to start before relying on it for conversions.', $timedOutTool),
            ];
        }

        return [
            'marker' => '✗',
            'line' => \sprintf('neither ImageMagick nor GraphicsMagick with WebP delegate found %s', $location),
            'recommendation' => 'MagickConverter needs `convert` (ImageMagick) or `gm` (GraphicsMagick) with WebP delegate support. Check $GLOBALS[TYPO3_CONF_VARS][GFX][processor_path] or PATH, or switch to PhpGdConverter.',
        ];
    }

    /**
     * @param list<string> $command
     *
     * @return array{timedOut: bool, exitCode: int, output: string}
     */
    private static function runCommandWithTimeout(array $command, int $timeoutSeconds): array
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = @\proc_open($command, $descriptors, $pipes);
        if (!\is_resource($process)) {
            return ['timedOut' => false, 'exitCode' => -1, 'output' => ''];
        }
        \fclose($pipes[0]);
        \stream_set_blocking($pipes[1], false);
        \stream_set_blocking($pipes[2], false);

        $output = '';
        $deadline = \microtime(true) + $timeoutSeconds;
        while (true) {
            $output .= (string) \stream_get_contents($pipes[1]);
            \stream_get_contents($pipes[2]);
            $status = \proc_get_status($process);
            if (!$status['running']) {
                $output .= (string) \stream_get_contents($pipes[1]);
                \fclose($pipes[1]);
                \fclose($pipes[2]);
                \proc_close($process);

                return ['timedOut' => false, 'exitCode' => $status['exitcode'], 'output' => $output];
            }
            if (\microtime(true) >= $deadline) {
                \proc_terminate($process, 9);
                \fclose($pipes[1]);
                \fclose($pipes[2]);
                \proc_close($process);

                return ['timedOut' => true, 'exitCode' => -1, 'output' => $output];
            }
            \usleep(50_000);
        }
    }

    /**
     * @return array{marker: string, line: string, recommendation: string}
     */
    private function checkExternalConverter(string $parameters): array
    {
        $segments = \explode('|', $parameters);
        $validSegments = 0;
        foreach ($segments as $segment) {
            $parts = \explode('::', $segment, 2);
            if (2 !== \count($parts)) {
                continue;
            }
            $command = \trim($parts[1]);
            if ('' === $command) {
                continue;
            }
            ++$validSegments;

            if (2 !== \substr_count($command, '%s')) {
                return [
                    'marker' => '✗',
                    'line' => \sprintf('external converter command for %s lacks the required two %%s placeholders', \trim($parts[0])),
                    'recommendation' => \sprintf("ExternalConverter requires exactly two %%s placeholders (source, target) per mime type.\nFix the segment for '%s' in the parameters string.", \trim($parts[0])),
                ];
            }

            $binary = \strtok($command, " \t");
            if (false === $binary || '' === $binary) {
                continue;
            }
            if (!\is_executable($binary)) {
                return [
                    'marker' => '✗',
                    'line' => \sprintf('external binary not executable: %s', $binary),
                    'recommendation' => \sprintf('ExternalConverter is configured but the binary at "%s" is not executable. Install it (`apt install webp` for cwebp, `apt install libvips-tools` for vips) or correct the path in the parameters setting.', $binary),
                ];
            }
        }

        if (0 === $validSegments) {
            return [
                'marker' => '✗',
                'line' => 'no parseable `mime/type::command` segment found in parameters',
                'recommendation' => "ExternalConverter parameters must look like 'image/jpeg::/usr/bin/cwebp -q 85 %s -o %s|image/png::...'.\nCheck the parameters setting in the extension config.",
            ];
        }

        return [
            'marker' => '✓',
            'line' => 'external converter binary is executable',
            'recommendation' => '',
        ];
    }

    /**
     * @return array{marker: string, line: string, recommendation: string}
     */
    private function checkVipsConverter(): array
    {
        if (!\extension_loaded('ffi')) {
            return [
                'marker' => '✗',
                'line' => 'PHP ext-ffi is not loaded',
                'recommendation' => 'VipsConverter (2.x) calls libvips via FFI. Enable extension=ffi in php.ini.',
            ];
        }

        $ffiEnable = \strtolower((string) \ini_get('ffi.enable'));
        if ('preload' === $ffiEnable) {
            return [
                'marker' => '✗',
                'line' => 'ffi.enable is set to "preload" — jcupitt/vips does not support FFI preloading',
                'recommendation' => 'php-vips 2.x requires FFI enabled globally. Set `ffi.enable=true` in php.ini (not `preload`).',
            ];
        }
        if (false === \filter_var($ffiEnable, \FILTER_VALIDATE_BOOLEAN, \FILTER_NULL_ON_FAILURE) || !\filter_var($ffiEnable, \FILTER_VALIDATE_BOOLEAN)) {
            return [
                'marker' => '✗',
                'line' => \sprintf('ext-ffi loaded but ffi.enable=%s (disabled)', $ffiEnable),
                'recommendation' => 'VipsConverter needs `ffi.enable=true` in php.ini. Current value is "' . $ffiEnable . '".',
            ];
        }

        $warningSuffix = '';
        if (\PHP_VERSION_ID >= 80300 && -1 !== (int) \ini_get('zend.max_allowed_stack_size')) {
            // jcupitt/vips runs FFI callbacks off the main thread, which trips PHP 8.3+ default stack-size limits.
            $warningSuffix = ' (warning: php-vips 2.x recommends zend.max_allowed_stack_size=-1 on PHP 8.3+, see php-vips README)';
        }

        if (!\class_exists(\Jcupitt\Vips\Image::class)) {
            return [
                'marker' => '✗',
                'line' => 'jcupitt/vips composer package is not installed',
                'recommendation' => 'VipsConverter needs the jcupitt/vips library. Run `composer require jcupitt/vips` in the project root. Also install the libvips shared library on the host (e.g. `apt install libvips-tools` on Debian/Ubuntu, which pulls in the correct libvips42/libvips42t64 runtime as a dependency).',
            ];
        }

        $libvipsVersion = '';
        try {
            $libvipsVersion = \Jcupitt\Vips\Config::version();
        } catch (\Throwable) {
            return [
                'marker' => '✗',
                'line' => 'jcupitt/vips is loaded but cannot reach the libvips shared library via FFI',
                'recommendation' => 'jcupitt/vips loaded but `Vips\\Config::version()` failed. The libvips shared library is missing or unreadable on the host. Install it (e.g. `apt install libvips-tools` on Debian/Ubuntu — pulls in libvips42/libvips42t64 as a dependency — or `brew install vips` on macOS).',
            ];
        }

        return [
            'marker' => '' === $warningSuffix ? '✓' : '!',
            'line' => \sprintf('libvips %s available via ext-ffi + jcupitt/vips%s', $libvipsVersion ?: 'unknown', $warningSuffix),
            'recommendation' => '' === $warningSuffix ? '' : 'Set `zend.max_allowed_stack_size=-1` in php.ini. Background: php-vips runs FFI callbacks off the main thread, which confuses PHP 8.3+ stack-size limits and may cause spurious failures.',
        ];
    }

    /**
     * @return array{marker: string, line: string, recommendation: string}
     */
    private function checkCustomConverter(string $converterClass): array
    {
        if (!\class_exists($converterClass)) {
            return [
                'marker' => '✗',
                'line' => \sprintf('configured converter class %s does not exist', $converterClass),
                'recommendation' => \sprintf('The configured converter class "%s" cannot be autoloaded. Check the FQCN spelling in the extension settings.', $converterClass),
            ];
        }
        if (!\is_subclass_of($converterClass, Converter::class)) {
            return [
                'marker' => '✗',
                'line' => \sprintf('%s does not implement %s', $converterClass, Converter::class),
                'recommendation' => \sprintf('"%s" is loadable but does not implement Plan2net\\Webp\\Converter\\Converter. Replace it with a class that does, or revert to a built-in converter.', $converterClass),
            ];
        }

        return [
            'marker' => '✓',
            'line' => \sprintf('custom converter %s found and implements the Converter interface', $converterClass),
            'recommendation' => '',
        ];
    }

    private function checkParameterParsing(SymfonyStyle $io): void
    {
        foreach ($this->configuration->getMimeTypes() as $mimeType) {
            $resolved = $this->webpService->getParametersForMimeType($mimeType);
            if (null === $resolved) {
                $this->writeStatus($io, '!', \sprintf('parameters for %s could not be resolved — falls back to old single-options format', $mimeType));
                ++$this->warningCount;
                $this->captureFirstFailure(\sprintf(
                    "Converter parameters cannot be resolved for mime type %s.\nThe parameters string should look like 'image/jpeg::-quality 85|image/png::-quality 75'. Check the parameters setting in the extension config.",
                    $mimeType,
                ));
            }
        }
    }

    private function reportAsyncPipeline(SymfonyStyle $io): void
    {
        $this->writeHeader($io, 'Async pipeline');

        if (!$this->configuration->isAsync()) {
            $io->writeln('· async = 0 (synchronous mode) — nothing to check');

            return;
        }

        $queueQuery = $this->connectionPool->getQueryBuilderForTable('tx_webp_queue');
        $queueSize = (int) $queueQuery
            ->count('uid')
            ->from('tx_webp_queue')
            ->executeQuery()
            ->fetchOne();
        $io->writeln(\sprintf('· queue size: %d', $queueSize));

        $oldestEnqueuedAt = null;
        if ($queueSize > 0) {
            $oldestQuery = $this->connectionPool->getQueryBuilderForTable('tx_webp_queue');
            $oldestEnqueuedAt = (int) $oldestQuery
                ->selectLiteral('MIN(enqueued_at)')
                ->from('tx_webp_queue')
                ->executeQuery()
                ->fetchOne();
            $io->writeln(\sprintf('· oldest entry: %s ago', $this->humanAge(\time() - $oldestEnqueuedAt)));
        }

        if (!$this->schedulerTableExists()) {
            $io->writeln('· scheduler check skipped (tx_scheduler_task not present) — drain the queue via `webp:process-queue` from a system cron');

            return;
        }

        $taskRow = $this->findWebpScheduledTaskRow();
        $taskFound = null !== $taskRow;
        $taskDisabled = $taskFound && (bool) $taskRow['disable'];
        $lastExecutionTime = $taskFound ? (int) $taskRow['lastexecution_time'] : 0;

        if ($taskFound) {
            $io->writeln(\sprintf(
                '· scheduler task: %s, last run %s ago',
                $taskDisabled ? 'DISABLED' : 'enabled',
                0 === $lastExecutionTime ? 'never' : $this->humanAge(\time() - $lastExecutionTime),
            ));
        }

        if (!$taskFound) {
            $this->writeStatus($io, '!', 'no ProcessWebpQueueTask registered in the scheduler');
            ++$this->warningCount;
            $this->captureFirstFailure("Async mode is enabled but no ProcessWebpQueueTask scheduler entry exists.\nEither create a scheduler task for Plan2net\\Webp\\Task\\ProcessWebpQueueTask, or invoke `vendor/bin/typo3 webp:process-queue` from a system cron.");
        }

        $stale = $queueSize > 0
            && null !== $oldestEnqueuedAt
            && (\time() - $oldestEnqueuedAt) > 3600
            && (!$taskFound || $taskDisabled || (\time() - $lastExecutionTime) > 3600);

        if ($stale) {
            $this->writeStatus($io, '✗', 'queue is not draining (oldest entry > 1h and no recent scheduler run)');
            ++$this->failureCount;
            $this->captureFirstFailure("Async queue has entries older than 1 hour and no recent scheduler activity.\nRun `vendor/bin/typo3 webp:process-queue` manually to confirm the converter still works, then check the scheduler task / cron entry.");
        }
    }

    private function schedulerTableExists(): bool
    {
        try {
            $connection = $this->connectionPool->getConnectionForTable('tx_scheduler_task');

            return $connection->createSchemaManager()->tablesExist(['tx_scheduler_task']);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array{disable: int, lastexecution_time: int}|null
     */
    private function findWebpScheduledTaskRow(): ?array
    {
        $hasTasktypeColumn = $this->schedulerHasTasktypeColumn();
        $columns = ['disable', 'lastexecution_time', 'serialized_task_object'];
        if ($hasTasktypeColumn) {
            $columns[] = 'tasktype';
        }

        $query = $this->connectionPool->getQueryBuilderForTable('tx_scheduler_task');
        $rows = $query
            ->select(...$columns)
            ->from('tx_scheduler_task')
            ->executeQuery()
            ->fetchAllAssociative();

        $taskClass = 'Plan2net\\Webp\\Task\\ProcessWebpQueueTask';
        foreach ($rows as $row) {
            $tasktype = (string) ($row['tasktype'] ?? '');
            $blob = (string) ($row['serialized_task_object'] ?? '');
            $matches = $tasktype === $taskClass
                || \str_contains($blob, $taskClass)
                || \str_contains($blob, 'Plan2net\\\\Webp\\\\Task\\\\ProcessWebpQueueTask');
            if (!$matches) {
                continue;
            }

            return [
                'disable' => (int) $row['disable'],
                'lastexecution_time' => (int) $row['lastexecution_time'],
            ];
        }

        return null;
    }

    private function schedulerHasTasktypeColumn(): bool
    {
        try {
            $connection = $this->connectionPool->getConnectionForTable('tx_scheduler_task');
            foreach ($connection->createSchemaManager()->listTableColumns('tx_scheduler_task') as $column) {
                if ('tasktype' === $column->getName()) {
                    return true;
                }
            }
        } catch (\Throwable) {
        }

        return false;
    }

    private function humanAge(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . 's';
        }
        if ($seconds < 3600) {
            return \intdiv($seconds, 60) . 'm';
        }
        if ($seconds < 86400) {
            return \intdiv($seconds, 3600) . 'h';
        }

        return \intdiv($seconds, 86400) . 'd';
    }

    private function reportFailedAttempts(SymfonyStyle $io): void
    {
        $this->writeHeader($io, 'Failed attempts');

        $countQuery = $this->connectionPool->getQueryBuilderForTable('tx_webp_failed');
        $totalCount = (int) $countQuery
            ->count('uid')
            ->from('tx_webp_failed')
            ->executeQuery()
            ->fetchOne();

        $io->writeln(\sprintf('· tx_webp_failed rows: %d', $totalCount));

        if (0 === $totalCount) {
            return;
        }

        $recentQuery = $this->connectionPool->getQueryBuilderForTable('tx_webp_failed');
        $recent = $recentQuery
            ->select('file_id', 'configuration_hash', 'configuration')
            ->from('tx_webp_failed')
            ->orderBy('uid', 'DESC')
            ->setMaxResults(5)
            ->executeQuery()
            ->fetchAllAssociative();

        $io->writeln('· most recent (up to 5):');
        foreach ($recent as $row) {
            $io->writeln(\sprintf(
                '    file_id=%d ⋅ hash=%s',
                (int) $row['file_id'],
                $row['configuration_hash'],
            ));
        }

        $dominantQuery = $this->connectionPool->getQueryBuilderForTable('tx_webp_failed');
        $dominant = $dominantQuery
            ->select('configuration_hash')
            ->addSelectLiteral('COUNT(*) AS hash_count')
            ->from('tx_webp_failed')
            ->groupBy('configuration_hash')
            ->orderBy('hash_count', 'DESC')
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        if (false !== $dominant && (int) $dominant['hash_count'] >= \intdiv($totalCount + 1, 2)) {
            $this->writeStatus($io, '!', \sprintf(
                'configuration_hash %s accounts for %d of %d failed attempts',
                $dominant['configuration_hash'],
                (int) $dominant['hash_count'],
                $totalCount,
            ));
            ++$this->warningCount;
            $this->captureFirstFailure(\sprintf(
                "More than half of the failed-conversion cache rows share one configuration_hash (%s).\nThe parameters in that configuration are likely wrong for the target images. Adjust the parameters string and run `TRUNCATE tx_webp_failed;` (or DELETE WHERE configuration_hash = '%s') to retry.",
                $dominant['configuration_hash'],
                $dominant['configuration_hash'],
            ));
        }
    }

    private function resolveProbeBaseUrl(InputInterface $input, SymfonyStyle $io): ?string
    {
        $explicit = $input->getOption('url');
        if (\is_string($explicit) && '' !== $explicit) {
            return $explicit;
        }

        $sites = $this->siteFinder->getAllSites();
        if ([] === $sites) {
            return null;
        }

        if (1 === \count($sites)) {
            $only = \reset($sites);

            return (string) $only->getBase();
        }

        $this->writeHeader($io, 'Delivery probe');
        $this->writeStatus($io, '!', 'multiple sites configured — auto-detection would have to guess. Pass --url to choose which site to probe:');
        foreach ($sites as $identifier => $site) {
            $io->writeln(\sprintf('    --url=%s ⋅ site identifier: %s', (string) $site->getBase(), $identifier));
        }
        ++$this->warningCount;
        $this->captureFirstFailure("Multiple sites are configured; pass --url=<base> to pick one for the HTTP probe.\nExample: vendor/bin/typo3 webp:diagnose --url=https://example.com");

        return null;
    }

    private function reportDeliveryProbe(SymfonyStyle $io, string $baseUrl, InputInterface $input): void
    {
        $this->writeHeader($io, 'Delivery probe');

        $probeTarget = $this->pickProbeTarget();
        if (null === $probeTarget) {
            $this->writeStatus($io, '!', 'no original/.webp sibling pair available on disk — probe skipped');
            ++$this->warningCount;

            return;
        }

        $publicUrl = $probeTarget->getPublicUrl();
        if (null === $publicUrl || '' === $publicUrl) {
            $this->writeStatus($io, '!', 'probe target has no public URL (storage not public?) — probe skipped');
            ++$this->warningCount;

            return;
        }

        $publicParts = \parse_url($publicUrl);
        if (\is_array($publicParts) && !empty($publicParts['scheme'])) {
            $probeUrl = $publicUrl;
            $io->writeln(\sprintf('· probing absolute URL from non-Local driver: %s', $probeUrl));
            $baseHost = \parse_url($baseUrl, \PHP_URL_HOST);
            if (\is_string($baseHost) && $baseHost !== ($publicParts['host'] ?? null)) {
                $io->writeln(\sprintf('  ↳ note: probe origin %s differs from your TYPO3 base host %s — Accept-rewrite checks below describe THAT origin\'s behaviour, not your TYPO3 webserver', $publicParts['host'] ?? '?', $baseHost));
            }
        } else {
            $baseParts = \parse_url($baseUrl);
            if (!\is_array($baseParts) || empty($baseParts['scheme']) || empty($baseParts['host'])) {
                $this->writeStatus($io, '!', \sprintf('could not parse base URL %s — probe skipped', $baseUrl));
                ++$this->warningCount;

                return;
            }
            $origin = \sprintf(
                '%s://%s%s',
                $baseParts['scheme'],
                $baseParts['host'],
                isset($baseParts['port']) ? ':' . $baseParts['port'] : '',
            );
            $probeUrl = $origin . '/' . \ltrim($publicUrl, '/');
            $io->writeln(\sprintf('· probing %s', $probeUrl));
        }

        $timeout = \max(1, (int) ($input->getOption('probe-timeout') ?: 10));
        $options = [
            'timeout' => $timeout,
            'allow_redirects' => false,
            'verify' => !(bool) $input->getOption('insecure'),
            'http_errors' => false,
            'headers' => [],
        ];

        $webpResponse = $this->probe($probeUrl, 'image/webp,*/*', $options, $io);
        $originalResponse = $this->probe($probeUrl, '*/*', $options, $io);

        if (null === $webpResponse || null === $originalResponse) {
            return;
        }

        $webpType = $this->contentType($webpResponse);
        $originalType = $this->contentType($originalResponse);
        $io->writeln(\sprintf('· with %-20s → %s', 'Accept image/webp', $webpType));
        $io->writeln(\sprintf('· with %-20s → %s', 'Accept */*', $originalType));

        $verdict = match (true) {
            'image/webp' === $webpType && 'image/webp' !== $originalType => 'rewrite-working',
            'image/webp' !== $webpType && 'image/webp' !== $originalType => 'no-rewrite',
            'image/webp' === $webpType && 'image/webp' === $originalType => 'unconditional-rewrite',
            default => 'inconclusive',
        };

        match ($verdict) {
            'rewrite-working' => $this->writeStatus($io, '✓', 'Accept-header rewrite is working'),
            'no-rewrite' => $this->failNoRewrite($io),
            'unconditional-rewrite' => $this->failUnconditionalRewrite($io),
            'inconclusive' => $this->warnInconclusiveRewrite($io),
        };

        if ('rewrite-working' !== $verdict) {
            return;
        }

        $vary = $webpResponse->getHeaderLine('Vary');
        if ('' === $vary || !\str_contains(\strtolower($vary), 'accept')) {
            $this->writeStatus($io, '!', 'Vary: Accept header missing on webp response — any CDN in front will cache-poison');
            ++$this->warningCount;
            $this->captureFirstFailure("The webserver returned a .webp variant but did not add `Vary: Accept`.\nWithout that header any caching layer (Varnish, CloudFront, Cloudflare) will serve the WebP response to clients that did not request it. Add `Vary: Accept` to your rewrite rule.");
        }
    }

    private function pickProbeTarget(): ?FileInterface
    {
        $processedQuery = $this->connectionPool->getQueryBuilderForTable('sys_file_processedfile');
        $candidates = $processedQuery
            ->select('p1.uid')
            ->from('sys_file_processedfile', 'p1')
            ->innerJoin(
                'p1',
                'sys_file_processedfile',
                'p2',
                'p1.storage = p2.storage AND p2.identifier = CONCAT(p1.identifier, \'.webp\')',
            )
            ->where($processedQuery->expr()->notLike('p1.identifier', $processedQuery->createNamedParameter('%.webp')))
            ->orderBy('p1.uid', 'ASC')
            ->setMaxResults(20)
            ->executeQuery()
            ->fetchAllAssociative();

        foreach ($candidates as $candidate) {
            try {
                $processedFile = $this->processedFileRepository->findByUid((int) $candidate['uid']);
            } catch (\Exception) {
                continue;
            }
            if (null === $processedFile) {
                continue;
            }
            if ($this->siblingFileExists($processedFile)) {
                return $processedFile;
            }
        }

        $originalQuery = $this->connectionPool->getQueryBuilderForTable('sys_file');
        $originalCandidates = $originalQuery
            ->select('s.uid')
            ->from('sys_file', 's')
            ->innerJoin(
                's',
                'sys_file',
                'w',
                's.storage = w.storage AND w.identifier = CONCAT(s.identifier, \'.webp\') AND w.extension = \'webp\'',
            )
            ->where(
                $originalQuery->expr()->in(
                    's.extension',
                    $originalQuery->createNamedParameter(['jpg', 'jpeg', 'png', 'gif'], Connection::PARAM_STR_ARRAY),
                ),
            )
            ->orderBy('s.uid', 'ASC')
            ->setMaxResults(20)
            ->executeQuery()
            ->fetchAllAssociative();

        foreach ($originalCandidates as $candidate) {
            try {
                $original = $this->resourceFactory->getFileObject((int) $candidate['uid']);
            } catch (\Exception) {
                continue;
            }
            if ($this->siblingFileExists($original)) {
                return $original;
            }
        }

        return null;
    }

    private function siblingFileExists(FileInterface $file): bool
    {
        try {
            return $file->getStorage()->hasFile($file->getIdentifier() . '.webp');
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * @param array<string, mixed> $options
     */
    private function probe(string $url, string $accept, array $options, SymfonyStyle $io): ?ResponseInterface
    {
        $options['headers']['Accept'] = $accept;
        try {
            return $this->requestFactory->request($url, 'HEAD', $options);
        } catch (ConnectException $exception) {
            $this->writeStatus($io, '!', \sprintf('could not connect — wrong host, firewall, or container can\'t reach external URL (%s)', $exception->getMessage()));
        } catch (GuzzleException $exception) {
            if (\str_contains($exception->getMessage(), 'timed out') || \str_contains($exception->getMessage(), 'timeout')) {
                $this->writeStatus($io, '!', \sprintf('no response within %ds — try --probe-timeout=30, or run from a host that can reach this site', (int) $options['timeout']));
            } elseif (\str_contains(\strtolower($exception->getMessage()), 'ssl') || \str_contains(\strtolower($exception->getMessage()), 'certificate')) {
                $this->writeStatus($io, '!', 'TLS certificate verification failed — use --insecure for self-signed dev certs');
            } else {
                $this->writeStatus($io, '!', \sprintf('probe HTTP error: %s', $exception->getMessage()));
            }
        }

        ++$this->warningCount;
        $this->captureFirstFailure("HTTP probe could not reach the configured URL.\nCheck the --url value, that the container can reach the host, and TLS settings (--insecure for self-signed dev).");

        return null;
    }

    private function contentType(ResponseInterface $response): string
    {
        $status = $response->getStatusCode();
        if ($status >= 400) {
            return \sprintf('HTTP %d', $status);
        }
        $header = $response->getHeaderLine('Content-Type');
        if ('' === $header) {
            return '(no Content-Type)';
        }
        $semicolon = \strpos($header, ';');

        return false === $semicolon ? $header : \substr($header, 0, $semicolon);
    }

    private function failNoRewrite(SymfonyStyle $io): void
    {
        $this->writeStatus($io, '✗', 'webserver returns the original mime type even when the client accepts webp — no rewrite configured');
        ++$this->failureCount;
        $this->captureFirstFailure("The webserver is not rewriting requests to .webp siblings.\nAdd an Accept-header content-negotiation rule to your webserver config. See README → 'Webserver configuration' for Apache, nginx, and Caddy snippets.");
    }

    private function failUnconditionalRewrite(SymfonyStyle $io): void
    {
        $this->writeStatus($io, '✗', 'webserver returns image/webp regardless of Accept header — unconditional rewrite (breaks clients that did not ask for webp)');
        ++$this->failureCount;
        $this->captureFirstFailure("The webserver rewrites every image request to .webp, even when the client did not send `Accept: image/webp`.\nThe rewrite must be gated by the Accept header. Check your rewrite rule for a missing `RewriteCond %{HTTP_ACCEPT} image/webp` (Apache) or `if (\$http_accept ~* webp)` (nginx).");
    }

    private function warnInconclusiveRewrite(SymfonyStyle $io): void
    {
        $this->writeStatus($io, '!', 'probe response is inconclusive (4xx/5xx) — file may not exist at that URL or is behind auth');
        ++$this->warningCount;
        $this->captureFirstFailure("Delivery probe returned a non-2xx response.\nUse --url with a known-good public image URL, or verify the probe target's public URL by hand.");
    }

    private function reportFileDeepDive(SymfonyStyle $io, int $fileUid): void
    {
        $this->writeHeader($io, \sprintf('File deep dive (#%d)', $fileUid));

        try {
            $file = $this->resourceFactory->getFileObject($fileUid);
        } catch (FileDoesNotExistException) {
            $io->error(\sprintf('sys_file uid %d not found', $fileUid));
            ++$this->failureCount;
            $this->captureFirstFailure(\sprintf('--file=%d refers to a sys_file row that does not exist. Pick a valid sys_file UID and rerun.', $fileUid));

            return;
        } catch (\Exception $exception) {
            $io->error(\sprintf('Could not load sys_file #%d: %s', $fileUid, $exception->getMessage()));
            ++$this->failureCount;
            $this->captureFirstFailure(\sprintf("sys_file #%d could not be loaded (%s).\nThe row exists but its storage or driver is misconfigured. Check the Storages section above for related warnings.", $fileUid, $exception::class));

            return;
        }

        try {
            $storage = $file->getStorage();
            $io->writeln(\sprintf('· storage:    #%d %s (%s)', $storage->getUid(), $storage->getName(), $storage->getDriverType()));
            $io->writeln(\sprintf('· identifier: %s', $file->getIdentifier()));
            $io->writeln(\sprintf('· mime type:  %s', $file->getMimeType()));
            $io->writeln(\sprintf('· sha1:       %s', $file->getSha1()));
            $io->writeln(\sprintf('· size:       %d bytes', $file->getSize()));

            $storageEnabled = StorageWebpMode::isEnabledFor($storage);
            $io->writeln(\sprintf('· storage opt-in: %s', $storageEnabled ? '✓ enabled' : '✗ off'));

            $mimeSupported = $this->configuration->isSupportedMimeType($file->getMimeType());
            $io->writeln(\sprintf('· mime supported: %s', $mimeSupported ? '✓ yes' : '✗ no (not in mime_types setting)'));

            $directSibling = $this->findDirectSibling($storage->getUid(), $file->getIdentifier() . '.webp');
            $processedSibling = $this->findProcessedSibling($fileUid);

            $io->writeln(\sprintf('· source-folder sibling: %s', null === $directSibling ? '✗ none' : \sprintf('✓ sys_file #%d', $directSibling)));
            $io->writeln(\sprintf('· processed sibling:     %s', [] === $processedSibling ? '✗ none' : \sprintf('✓ %d row(s) in sys_file_processedfile', \count($processedSibling))));

            $failedRows = $this->findFailedRows($fileUid);
            if ([] !== $failedRows) {
                $io->writeln(\sprintf('· tx_webp_failed rows: %d', \count($failedRows)));
                foreach ($failedRows as $row) {
                    $io->writeln(\sprintf('    hash=%s', $row['configuration_hash']));
                }
                ++$this->failureCount;
                $this->captureFirstFailure(\sprintf(
                    "File #%d has %d row(s) in tx_webp_failed — the converter previously gave up on this file.\nRun: DELETE FROM tx_webp_failed WHERE file_id = %d;\nThen re-render the image (e.g. clear processed files / BE preview) to retry conversion.",
                    $fileUid,
                    \count($failedRows),
                    $fileUid,
                ));

                return;
            }

            if (null === $directSibling && [] === $processedSibling && $mimeSupported && $storageEnabled) {
                $this->writeStatus($io, '!', 'no sibling in either table and no failure recorded — sibling has never been generated');
                ++$this->warningCount;
                $this->captureFirstFailure(\sprintf(
                    "File #%d has no .webp sibling in either sys_file or sys_file_processedfile, and there is no failure recorded.\nThe file has likely never been rendered through TYPO3's image pipeline since the extension was installed. Trigger a re-render (e.g. clear processed files in Install Tool) to generate it.",
                    $fileUid,
                ));
            }
        } catch (\Exception $exception) {
            $io->error(\sprintf('File deep dive aborted: %s (%s)', $exception->getMessage(), $exception::class));
            ++$this->failureCount;
            $this->captureFirstFailure(\sprintf("File deep dive for #%d aborted with %s.\nThe storage or driver behind this file is likely misconfigured — see the Storages section.", $fileUid, $exception::class));
        }
    }

    private function findDirectSibling(int $storageUid, string $siblingIdentifier): ?int
    {
        $query = $this->connectionPool->getQueryBuilderForTable('sys_file');
        $row = $query
            ->select('uid')
            ->from('sys_file')
            ->where(
                $query->expr()->eq('storage', $query->createNamedParameter($storageUid, Connection::PARAM_INT)),
                $query->expr()->eq('identifier', $query->createNamedParameter($siblingIdentifier)),
                $query->expr()->eq('extension', $query->createNamedParameter('webp')),
            )
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        return false === $row ? null : (int) $row['uid'];
    }

    /**
     * @return list<array{uid: int, identifier: string}>
     */
    private function findProcessedSibling(int $originalUid): array
    {
        $query = $this->connectionPool->getQueryBuilderForTable('sys_file_processedfile');
        $rows = $query
            ->select('uid', 'identifier')
            ->from('sys_file_processedfile')
            ->where(
                $query->expr()->eq('original', $query->createNamedParameter($originalUid, Connection::PARAM_INT)),
                $query->expr()->like('identifier', $query->createNamedParameter('%.webp')),
            )
            ->executeQuery()
            ->fetchAllAssociative();

        /** @var list<array{uid: int, identifier: string}> $normalized */
        $normalized = \array_map(
            static fn (array $row): array => ['uid' => (int) $row['uid'], 'identifier' => (string) $row['identifier']],
            $rows,
        );

        return $normalized;
    }

    /**
     * @return list<array{configuration_hash: string}>
     */
    private function findFailedRows(int $fileUid): array
    {
        $query = $this->connectionPool->getQueryBuilderForTable('tx_webp_failed');
        $rows = $query
            ->select('configuration_hash')
            ->from('tx_webp_failed')
            ->where($query->expr()->eq('file_id', $query->createNamedParameter($fileUid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchAllAssociative();

        /** @var list<array{configuration_hash: string}> $normalized */
        $normalized = \array_map(
            static fn (array $row): array => ['configuration_hash' => (string) $row['configuration_hash']],
            $rows,
        );

        return $normalized;
    }

    private function emitRecommendation(SymfonyStyle $io): void
    {
        $this->writeHeader($io, 'Recommendation');
        if (0 === $this->failureCount && 0 === $this->warningCount) {
            $io->writeln('<fg=green>No issues found.</>');
            $io->newLine();

            return;
        }

        $color = $this->failureCount > 0 ? 'red' : 'yellow';
        $summary = $this->failureCount > 0
            ? \sprintf('%s, %s.', $this->pluralize($this->failureCount, 'failure'), $this->pluralize($this->warningCount, 'warning'))
            : \sprintf('No failures, %s.', $this->pluralize($this->warningCount, 'warning'));
        $io->writeln(\sprintf('<fg=%s>%s</>', $color, $summary));

        if (null !== $this->firstFailure) {
            foreach (\explode("\n", $this->firstFailure) as $line) {
                $io->writeln(\sprintf('<fg=%s>%s</>', $color, $line));
            }
        }
        $io->newLine();
    }

    private function captureFirstFailure(string $message): void
    {
        if (null === $this->firstFailure) {
            $this->firstFailure = $message;
        }
    }

    private function writeHeader(SymfonyStyle $io, string $title, string $style = 'fg=black;bg=cyan;options=bold'): void
    {
        $io->newLine();
        $io->writeln(\sprintf('<%s> %s </>', $style, $title));
        $io->newLine();
    }

    private function pluralize(int $count, string $singular): string
    {
        return \sprintf('%d %s%s', $count, $singular, 1 === $count ? '' : 's');
    }

    private function writeStatus(SymfonyStyle $io, string $symbol, string $message): void
    {
        $color = match ($symbol) {
            '✓' => 'green',
            '✗' => 'red',
            '!' => 'yellow',
            default => null,
        };

        if (null === $color) {
            $io->writeln(\sprintf('%s %s', $symbol, $message));

            return;
        }

        $io->writeln(\sprintf('<fg=%s>%s %s</>', $color, $symbol, $message));
    }
}
