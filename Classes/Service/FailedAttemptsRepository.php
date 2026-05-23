<?php

declare(strict_types=1);

namespace Plan2net\Webp\Service;

use Doctrine\DBAL\Exception;
use Plan2net\Webp\Format\OutputFormat;
use TYPO3\CMS\Core\Database\ConnectionPool;

final readonly class FailedAttemptsRepository
{
    public function __construct(
        private ConnectionPool $connectionPool,
    ) {
    }

    public function wasAttempted(int $fileUid, string $configuration, OutputFormat $format = OutputFormat::Webp): bool
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_webp_failed');

        try {
            return (bool) $queryBuilder->count('uid')
                ->from('tx_webp_failed')
                ->where(
                    $queryBuilder->expr()->eq('file_id', $fileUid),
                    $queryBuilder->expr()->eq(
                        'configuration_hash',
                        $queryBuilder->createNamedParameter(md5($configuration)),
                    ),
                    $queryBuilder->expr()->eq(
                        'format',
                        $queryBuilder->createNamedParameter($format->value),
                    ),
                )
                ->executeQuery()
                ->fetchOne();
        } catch (Exception) {
            return false;
        }
    }

    public function record(int $fileUid, string $configuration, OutputFormat $format = OutputFormat::Webp): void
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_webp_failed');
        $queryBuilder->insert('tx_webp_failed')
            ->values([
                'file_id' => $fileUid,
                'configuration' => $configuration,
                'configuration_hash' => md5($configuration),
                'format' => $format->value,
            ])
            ->executeStatement();
    }
}
