<?php

declare(strict_types=1);

namespace Plan2net\Webp\EventListener;

use Plan2net\Webp\Service\WebpSiblingFile;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Resource\Event\AfterFileMovedEvent;

#[AsEventListener('webp.after-file-moved')]
final readonly class AfterFileMoved
{
    public function __construct(
        private WebpSiblingFile $siblings,
    ) {
    }

    public function __invoke(AfterFileMovedEvent $event): void
    {
        $this->siblings->relocateAfterMove($event->getFile());
    }
}
