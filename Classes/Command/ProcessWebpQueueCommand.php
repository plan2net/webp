<?php

declare(strict_types=1);

namespace Plan2net\Webp\Command;

use Plan2net\Webp\Converter\Exception\ConvertedFileLargerThanOriginalException;
use Plan2net\Webp\Converter\Exception\WillNotRetryWithConfigurationException;
use Plan2net\Webp\Domain\Queue\WebpQueueRepository;
use Plan2net\Webp\Service\Configuration;
use Plan2net\Webp\Service\FolderScanner;
use Plan2net\Webp\Service\ProcessedFileWriter;
use Plan2net\Webp\Service\Webp as WebpService;
use Plan2net\Webp\Task\ProcessWebpQueueTask;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\ProcessedFileRepository;
use TYPO3\CMS\Core\Resource\ResourceFactory;

#[AsCommand(
    name: 'webp:process-queue',
    description: 'Drain the WebP conversion queue or sweep a filesystem folder.'
)]
final class ProcessWebpQueueCommand extends Command implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly WebpQueueRepository $queueRepository,
        private readonly ResourceFactory $resourceFactory,
        private readonly ProcessedFileRepository $processedFileRepository,
        private readonly WebpService $webpService,
        private readonly ProcessedFileWriter $processedFileWriter,
        private readonly Configuration $configuration,
        private readonly FolderScanner $folderScanner,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('batch', null, InputOption::VALUE_REQUIRED, 'Maximum number of queue entries to process per run', (string) ProcessWebpQueueTask::DEFAULT_BATCH_SIZE);
        $this->addOption('folder', null, InputOption::VALUE_REQUIRED, 'Filesystem folder to sweep (relative to public web root). When set, bypasses the queue.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $folder = $input->getOption('folder');
        if (\is_string($folder) && $folder !== '') {
            return $this->runFolderMode($folder, $output);
        }
        return $this->runQueueMode(\max(1, (int) $input->getOption('batch')), $output);
    }

    private function runQueueMode(int $batch, OutputInterface $output): int
    {
        $entries = $this->queueRepository->fetchBatch($batch);
        $throttleMs = $this->configuration->getAsyncThrottleMs();
        $lastIndex = \count($entries) - 1;

        foreach ($entries as $index => $entry) {
            $processedFileWebp = null;
            try {
                $originalFile = $this->resourceFactory->getFileObject($entry->originalFileId);
                $configuration = (array) \unserialize($entry->configuration, ['allowed_classes' => false]);
                $source = $this->resolveSource($entry->processedFileId, $originalFile);
                if ($source === null) {
                    continue;
                }
                $processedFileWebp = $this->processedFileRepository->findOneByOriginalFileAndTaskTypeAndConfiguration(
                    $originalFile,
                    $entry->taskType,
                    $configuration
                );
                if (!$this->webpService->needsReprocessing($processedFileWebp)) {
                    continue;
                }
                if ($source instanceof ProcessedFile && $source->isOutdated()) {
                    // Original changed between enqueue and now; the cached derivative is stale.
                    // Skip — the next render will reprocess and re-enqueue with current data.
                    continue;
                }
                $this->webpService->process($source, $processedFileWebp);
                $this->processedFileWriter->add($processedFileWebp, $entry->taskType, $configuration);
            } catch (WillNotRetryWithConfigurationException $e) {
                $this->logger?->notice($e->getMessage());
            } catch (ConvertedFileLargerThanOriginalException $e) {
                $this->logger?->warning($e->getMessage());
                if ($processedFileWebp instanceof ProcessedFile) {
                    try {
                        $processedFileWebp->delete(true);
                    } catch (\Throwable) {
                    }
                }
            } catch (\Throwable $e) {
                $this->logger?->error('webp queue: ' . $e->getMessage(), ['originalFileId' => $entry->originalFileId]);
            } finally {
                $this->queueRepository->remove($entry->uid);
                $this->applyThrottle($throttleMs, $index === $lastIndex);
            }
        }
        return Command::SUCCESS;
    }

    private function runFolderMode(string $folder, OutputInterface $output): int
    {
        $rootPath = $this->resolveAgainstWebRoot($folder);
        if ($rootPath === null) {
            $output->writeln(\sprintf('<error>Folder "%s" not found or outside the public web root</error>', $folder));
            return Command::FAILURE;
        }

        $mimeTypes = $this->configuration->getMimeTypes();
        $throttleMs = $this->configuration->getAsyncThrottleMs();
        $entries = \iterator_to_array($this->folderScanner->scan($rootPath, $mimeTypes), false);
        $lastIndex = \count($entries) - 1;

        foreach ($entries as $index => $entry) {
            try {
                $this->webpService->convertFilePath($entry['path'], $entry['path'] . '.webp', $entry['mimeType']);
            } catch (\Throwable $e) {
                $this->logger?->error('webp folder: ' . $e->getMessage(), ['path' => $entry['path']]);
            }
            $this->applyThrottle($throttleMs, $index === $lastIndex);
        }
        return Command::SUCCESS;
    }

    private function resolveSource(int $processedFileId, $originalFile): ?\TYPO3\CMS\Core\Resource\FileInterface
    {
        if (!$originalFile instanceof \TYPO3\CMS\Core\Resource\FileInterface) {
            return null;
        }
        if ($processedFileId === 0) {
            return $originalFile;
        }
        try {
            return $this->processedFileRepository->findByUid($processedFileId);
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveAgainstWebRoot(string $folder): ?string
    {
        $webRoot = \realpath(Environment::getPublicPath());
        if ($webRoot === false) {
            return null;
        }
        $candidate = \realpath($webRoot . '/' . \ltrim($folder, '/'));
        if (false === $candidate) {
            return null;
        }
        // Sibling-prefix attack guard: /path/public_evil must not match /path/public.
        if ($candidate !== $webRoot && !\str_starts_with($candidate, $webRoot . \DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $candidate;
    }

    private function applyThrottle(int $throttleMs, bool $isLast): void
    {
        if ($throttleMs <= 0 || $isLast) {
            return;
        }
        $min = \intdiv($throttleMs, 2);
        $max = \intdiv($throttleMs * 3, 2);
        \usleep(\random_int($min, $max) * 1000);
    }
}
