<?php

declare(strict_types=1);

namespace Plan2net\Webp\EventListener;

use Plan2net\Webp\Service\WebpSiblingFile;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Resource\Event\BeforeFileMovedEvent;

#[AsEventListener('webp.before-file-moved')]
final readonly class BeforeFileMoved
{
    public function __construct(
        private WebpSiblingFile $siblings,
    ) {
    }

    public function __invoke(BeforeFileMovedEvent $event): void
    {
        $this->siblings->captureBeforeMove($event->getFile());
    }
}
