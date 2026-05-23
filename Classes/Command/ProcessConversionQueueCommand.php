<?php

declare(strict_types=1);

namespace Plan2net\Webp\Command;

use Plan2net\Webp\Domain\Queue\ConversionQueueRepository;
use Plan2net\Webp\Format\SourceMimeType;
use Plan2net\Webp\Service\Configuration;
use Plan2net\Webp\Service\FolderScanner;
use Plan2net\Webp\Service\SiblingGenerator;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Attribute\AsNonSchedulableCommand;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Locking\Exception\LockAcquireWouldBlockException;
use TYPO3\CMS\Core\Locking\LockFactory;
use TYPO3\CMS\Core\Locking\LockingStrategyInterface;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\ProcessedFileRepository;
use TYPO3\CMS\Core\Resource\ResourceFactory;

#[AsCommand(
    name: 'webp:process-queue',
    description: 'Drain the conversion queue or sweep a filesystem folder.'
)]
#[AsNonSchedulableCommand]
final class ProcessConversionQueueCommand extends Command implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly ConversionQueueRepository $queueRepository,
        private readonly ResourceFactory $resourceFactory,
        private readonly ProcessedFileRepository $processedFileRepository,
        private readonly SiblingGenerator $siblingGenerator,
        private readonly Configuration $configuration,
        private readonly FolderScanner $folderScanner,
        private readonly LockFactory $lockFactory,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        // Inline default mirrors ProcessWebpQueueTask::DEFAULT_BATCH_SIZE.
        // Not a class-constant reference because the command must work even when
        // typo3/cms-scheduler is absent (the Task class extends AbstractTask).
        $this->addOption('batch', null, InputOption::VALUE_REQUIRED, 'Maximum number of queue entries to process per run', '50');
        $this->addOption('folder', null, InputOption::VALUE_REQUIRED, 'Filesystem folder to sweep (relative to public web root). When set, bypasses the queue.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $locker = $this->lockFactory->createLocker(
            'webp.queue.worker',
            LockingStrategyInterface::LOCK_CAPABILITY_EXCLUSIVE | LockingStrategyInterface::LOCK_CAPABILITY_NOBLOCK
        );
        try {
            $acquired = $locker->acquire(
                LockingStrategyInterface::LOCK_CAPABILITY_EXCLUSIVE | LockingStrategyInterface::LOCK_CAPABILITY_NOBLOCK
            );
        } catch (LockAcquireWouldBlockException) {
            $acquired = false;
        }
        if (!$acquired) {
            $output->writeln('<info>Another webp:process-queue is running; exiting.</info>');

            return Command::SUCCESS;
        }
        try {
            $folder = $input->getOption('folder');
            if (\is_string($folder) && '' !== $folder) {
                return $this->runFolderMode($folder, $output);
            }

            return $this->runQueueMode(\max(1, (int) $input->getOption('batch')), $output);
        } finally {
            $locker->release();
        }
    }

    private function runQueueMode(int $batch, OutputInterface $output): int
    {
        $entries = $this->queueRepository->fetchBatch($batch);
        $throttleMs = $this->configuration->getAsyncThrottleMs();
        $lastIndex = \count($entries) - 1;

        foreach ($entries as $index => $entry) {
            try {
                $originalFile = $this->resourceFactory->getFileObject($entry->originalFileId);
                if (!$originalFile instanceof File) {
                    continue;
                }
                $configuration = (array) \unserialize($entry->configuration, ['allowed_classes' => false]);
                $source = $this->resolveSource($entry->processedFileId, $originalFile);
                if (null === $source) {
                    continue;
                }
                if ($source instanceof ProcessedFile && $source->isOutdated()) {
                    // Original changed between enqueue and now; skip — next render re-enqueues.
                    continue;
                }
                // $entry->format restricts process() to this row's format; without it
                // a single drain would re-process every currently-enabled format.
                $this->siblingGenerator->process($originalFile, $source, $entry->taskType, $configuration, $entry->format);
            } catch (\Throwable $e) {
                $this->logger?->error('webp queue: ' . $e->getMessage(), ['originalFileId' => $entry->originalFileId, 'format' => $entry->format->value]);
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
        if (null === $rootPath) {
            $output->writeln(\sprintf('<error>Folder "%s" not found or outside the public web root</error>', $folder));

            return Command::FAILURE;
        }

        $throttleMs = $this->configuration->getAsyncThrottleMs();
        $enabledFormats = $this->configuration->getEnabledFormats();
        if ([] === $enabledFormats) {
            return Command::SUCCESS;
        }
        $mimeTypes = $this->mimeTypeUnion($enabledFormats);

        $entries = \iterator_to_array($this->folderScanner->scan($rootPath, $mimeTypes, $enabledFormats), false);
        $lastIndex = \count($entries) - 1;

        foreach ($entries as $index => $entry) {
            foreach ($entry['missingFormats'] as $format) {
                if (!$this->configuration->isSupportedMimeTypeFor($format, $entry['mimeType'])) {
                    continue;
                }
                try {
                    $this->siblingGenerator->convertFilePath($entry['path'], $entry['path'] . $format->suffix(), $entry['mimeType'], $format);
                } catch (\Throwable $e) {
                    $this->logger?->error('webp folder: ' . $e->getMessage(), ['path' => $entry['path'], 'format' => $format->value]);
                }
            }
            $this->applyThrottle($throttleMs, $index === $lastIndex);
        }

        return Command::SUCCESS;
    }

    /**
     * @param list<\Plan2net\Webp\Format\OutputFormat> $enabledFormats
     *
     * @return list<string>
     */
    private function mimeTypeUnion(array $enabledFormats): array
    {
        $union = [];
        foreach (SourceMimeType::all() as $mimeType) {
            foreach ($enabledFormats as $format) {
                if ($this->configuration->isSupportedMimeTypeFor($format, $mimeType)) {
                    $union[] = $mimeType;
                    break;
                }
            }
        }

        return $union;
    }

    private function resolveSource(int $processedFileId, FileInterface $originalFile): ?FileInterface
    {
        if (0 === $processedFileId) {
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
        if (false === $webRoot) {
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
