<?php

declare(strict_types=1);

namespace Plan2net\Webp\EventListener;

use Plan2net\Webp\Service\WebpSiblingFile;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Resource\Event\BeforeFileRenamedEvent;

#[AsEventListener('webp.before-file-renamed')]
final readonly class BeforeFileRenamed
{
    public function __construct(
        private WebpSiblingFile $siblings,
    ) {
    }

    public function __invoke(BeforeFileRenamedEvent $event): void
    {
        $this->siblings->captureBeforeMove($event->getFile());
    }
}
