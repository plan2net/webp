<?php

declare(strict_types=1);

namespace Plan2net\Webp\Updates;

use Plan2net\Webp\Format\OutputFormat;
use TYPO3\CMS\Core\Attribute\UpgradeWizard;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

#[UpgradeWizard('webp.removeSiblingRowsSharingOneFile')]
final class RemoveSiblingRowsSharingOneFileUpdate implements UpgradeWizardInterface
{
    private const TABLE = 'sys_file_processedfile';

    public function getTitle(): string
    {
        return 'webp: Remove sibling rows that share one file';
    }

    public function getDescription(): string
    {
        return 'Older versions of plan2net/webp wrote one sibling row per image quality, but all of these rows pointed to the same file. A row could then claim a file that holds another quality, so going back to an earlier quality kept the wrong sibling. This wizard removes these rows. The next render converts the image again with the current quality.';
    }

    public function executeUpdate(): bool
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable(self::TABLE);
        foreach ($this->sharedFiles() as $sharedFile) {
            $connection->delete(
                self::TABLE,
                ['storage' => (int) $sharedFile['storage'], 'identifier' => (string) $sharedFile['identifier']],
                ['storage' => Connection::PARAM_INT],
            );
        }

        return true;
    }

    public function updateNecessary(): bool
    {
        return [] !== $this->sharedFiles();
    }

    public function getPrerequisites(): array
    {
        return [];
    }

    /**
     * @return list<array{storage: int|string, identifier: string}>
     */
    private function sharedFiles(): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->getRestrictions()->removeAll();
        $siblingSuffixes = \array_map(
            static fn (OutputFormat $format): string => $queryBuilder->expr()->like(
                'identifier',
                $queryBuilder->createNamedParameter('%' . $queryBuilder->escapeLikeWildcards($format->suffix())),
            ),
            OutputFormat::cases(),
        );

        return $queryBuilder->select('storage', 'identifier')
            ->from(self::TABLE)
            ->where($queryBuilder->expr()->or(...$siblingSuffixes))
            ->groupBy('storage', 'identifier')
            ->having('COUNT(*) > 1')
            ->executeQuery()
            ->fetchAllAssociative();
    }
}
