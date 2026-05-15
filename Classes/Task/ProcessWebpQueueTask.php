<?php

declare(strict_types=1);

namespace Plan2net\Webp\Task;

use Plan2net\Webp\Command\ProcessWebpQueueCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\Task\AbstractTask;

final class ProcessWebpQueueTask extends AbstractTask
{
    public const DEFAULT_BATCH_SIZE = 50;

    public int $batchSize = self::DEFAULT_BATCH_SIZE;

    public function execute(): bool
    {
        $command = GeneralUtility::makeInstance(ProcessWebpQueueCommand::class);
        $input = new ArrayInput(['--batch' => (string) $this->batchSize]);

        return 0 === $command->run($input, new NullOutput());
    }
}
