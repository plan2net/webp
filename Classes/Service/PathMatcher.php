<?php

declare(strict_types=1);

namespace Plan2net\Webp\Service;

use TYPO3\CMS\Core\SingletonInterface;

final class PathMatcher implements SingletonInterface
{
    public function matches(string $path, string $prefix): bool
    {
        $normalizedPath = $this->normalizePath($path);
        $normalizedPrefix = $this->normalizePath($prefix);

        // Check if path starts with prefix and is either the same or has a following slash
        return str_starts_with($normalizedPath, $normalizedPrefix) && (
            $normalizedPath === $normalizedPrefix ||
            str_starts_with($normalizedPath, $normalizedPrefix . '/')
        );
    }

    public function matchesAny(string $path, array $patterns): bool
    {
        if (empty($patterns)) {
            return false;
        }

        foreach ($patterns as $pattern) {
            if ($this->matches($path, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function normalizePath(string $path): string
    {
        return trim(trim($path), '/');
    }
}
