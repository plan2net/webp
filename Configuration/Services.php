<?php

declare(strict_types=1);

namespace Plan2net\Webp;

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;
use TYPO3\CMS\Scheduler\AbstractAdditionalFieldProvider;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services();

    // Service classes that extend or implement types from TYPO3 packages we
    // treat as soft dependencies. Register only when the respective package
    // is installed; otherwise loading the file would fatal at the
    // extends/implements clause.

    if (\interface_exists(UpgradeWizardInterface::class)) {
        $services
            ->set(Updates\TruncateFailedAttemptsBeforeColumnResizeUpdate::class)
            ->autowire()
            ->tag('install.upgradewizard', ['identifier' => 'webp.truncateFailedAttemptsBeforeColumnResize']);
    }

    if (\class_exists(AbstractAdditionalFieldProvider::class)) {
        $services
            ->set(Task\ProcessWebpQueueTaskAdditionalFieldProvider::class)
            ->autowire()
            ->public();
    }
};
