<?php

declare(strict_types=1);

namespace Plan2net\Webp\EventListener;

use Plan2net\Webp\Service\WebpSiblingFile;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Resource\Event\AfterFileRenamedEvent;

#[AsEventListener('webp.after-file-renamed')]
final readonly class AfterFileRenamed
{
    public function __construct(
        private WebpSiblingFile $siblings,
    ) {
    }

    public function __invoke(AfterFileRenamedEvent $event): void
    {
        $this->siblings->relocateAfterMove($event->getFile());
    }
}
