<?php
declare(strict_types=1);

namespace Plan2net\Webp\Domain\Repository;

/**
 * FailedRepository.
 *
 * THX @ https://github.com/lochmueller
 */
class FailedRepository extends AbstractRepository
{
    protected const TABLE_NAME = 'tx_webp_failed';

    /**
     * Get the table name.
     *
     * @return string
     */
    protected function getTableName(): string
    {
        return TABLE_NAME;
    }

    /**
     * saveFailedAttempt.
     *
     * @return int
     */
    public function saveFailedAttempt(int $fileId, string $configuration): int
    {
        return $this->createQuery()
            ->insert(TABLE_NAME)
            ->values([
                'file_id' => $fileId,
                'configuration' => $configuration,
                'configuration_hash' => sha1($configuration)
            ])
            ->execute();
    }

    /**
     * hasFailedAttempt.
     *
     * @return bool
     */
    public function hasFailedAttempt(int $fileId, string $configuration): bool
    {
        return (bool)$this->createQuery()
            ->count('*')
            ->from(TABLE_NAME)
            ->where(
                $queryBuilder->expr()->eq('file_id', $fileId),
                $queryBuilder->expr()->eq('configuration_hash',
                    $queryBuilder->createNamedParameter(sha1($configuration)))
            )
            ->execute()
            ->fetchOne();
    }
}
