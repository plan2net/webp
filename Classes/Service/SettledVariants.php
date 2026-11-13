<?php

declare(strict_types=1);

namespace Plan2net\Webp\Service;

use Doctrine\DBAL\Exception;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ProcessedFile;

final class SettledVariants
{
    /**
     * @var array<int, array{
     *     fingerprint: string,
     *     variants: array<int, string>,
     * }>
     */
    private array $entries = [];

    public function __construct(
        private readonly FrontendInterface $cache,
        private readonly Configuration $configuration,
    ) {
    }

    public function contains(File $originalFile, ProcessedFile $variant): bool
    {
        $variantUid = (int) $variant->getUid();
        if (0 === $variantUid) {
            return false;
        }

        // Core can regenerate a variant under a new name (checksum) and keep its uid.
        return ($this->entryFor($originalFile)['variants'][$variantUid] ?? null) === $variant->getIdentifier();
    }

    public function add(File $originalFile, ProcessedFile $variant): void
    {
        $variantUid = (int) $variant->getUid();
        if (0 === $variantUid) {
            return;
        }

        $entry = $this->entryFor($originalFile);
        $entry['variants'][$variantUid] = $variant->getIdentifier();
        $this->entries[(int) $originalFile->getUid()] = $entry;

        try {
            $this->cache->set(self::identifier($originalFile), $entry);
        } catch (Exception) {
            // Cache table not created yet; the next render checks again.
        }
    }

    /**
     * @return array{
     *     fingerprint: string,
     *     variants: array<int, string>,
     * }
     */
    private function entryFor(File $originalFile): array
    {
        $uid = (int) $originalFile->getUid();
        $fingerprint = $this->fingerprint($originalFile);
        $entry = $this->entries[$uid] ?? $this->read($originalFile);
        if (!\is_array($entry) || ($entry['fingerprint'] ?? null) !== $fingerprint) {
            $entry = ['fingerprint' => $fingerprint, 'variants' => []];
        }

        return $this->entries[$uid] = $entry;
    }

    private function fingerprint(File $originalFile): string
    {
        return \sha1(\serialize([
            $originalFile->getSha1(),
            QualityOverride::forcedQualityFor($originalFile),
            $this->configuration->fingerprint(),
        ]));
    }

    private function read(File $originalFile): mixed
    {
        try {
            return $this->cache->get(self::identifier($originalFile));
        } catch (Exception) {
            return false;
        }
    }

    private static function identifier(File $originalFile): string
    {
        return 'original_' . (int) $originalFile->getUid();
    }
}
