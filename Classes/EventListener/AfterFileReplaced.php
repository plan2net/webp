<?php

declare(strict_types=1);

namespace Plan2net\Webp\EventListener;

use Plan2net\Webp\Service\SiblingFile;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Resource\Event\AfterFileReplacedEvent;

#[AsEventListener('webp.after-file-replaced')]
final readonly class AfterFileReplaced
{
    public function __construct(
        private SiblingFile $siblings,
    ) {
    }

    public function __invoke(AfterFileReplacedEvent $event): void
    {
        $this->siblings->deleteForReplacedFile($event->getFile());
    }
}
