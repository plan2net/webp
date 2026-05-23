<?php

declare(strict_types=1);

namespace Plan2net\Webp\EventListener;

use Plan2net\Webp\Service\SiblingFile;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Resource\Event\AfterFileDeletedEvent;

#[AsEventListener('webp.after-file-deleted')]
final readonly class AfterFileDeleted
{
    public function __construct(
        private SiblingFile $siblings,
    ) {
    }

    public function __invoke(AfterFileDeletedEvent $event): void
    {
        $this->siblings->deleteForDeletedFile($event->getFile());
    }
}
