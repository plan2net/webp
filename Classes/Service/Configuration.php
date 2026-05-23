<?php

declare(strict_types=1);

namespace Plan2net\Webp\Service;

use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

final readonly class Configuration
{
    public function __construct(
        private ExtensionConfiguration $extensionConfiguration,
    ) {
    }

    public function getConverter(): string
    {
        return $this->stringValue('converter');
    }

    public function getParameters(): string
    {
        return $this->stringValue('parameters');
    }

    public function isSupportedMimeType(string $mimeType): bool
    {
        $list = $this->stringValue('mime_types');
        if ('' === $list) {
            return false;
        }

        $configured = array_map(
            static fn (string $value): string => strtolower(trim($value)),
            explode(',', $list),
        );

        return in_array(strtolower($mimeType), $configured, true);
    }

    public function isConvertAll(): bool
    {
        return $this->boolValue('convert_all');
    }

    public function getExcludeDirectories(): array
    {
        $raw = $this->stringValue('exclude_directories');
        if ('' === $raw) {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', explode(';', $raw)),
            static fn (string $value): bool => '' !== $value,
        ));
    }

    public function isSilent(): bool
    {
        return $this->boolValue('silent');
    }

    public function isUseSystemSettings(): bool
    {
        return $this->boolValue('use_system_settings');
    }

    public function isHideSiblings(): bool
    {
        return $this->boolValue('hide_webp');
    }

    public function isAsync(): bool
    {
        return $this->boolValue('async');
    }

    public function getAsyncThrottleMs(): int
    {
        return (int) $this->stringValue('async_throttle_ms');
    }

    /**
     * @return list<string>
     */
    public function getMimeTypes(): array
    {
        $raw = $this->stringValue('mime_types');
        if ('' === $raw) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (string $value): string => strtolower(trim($value)), explode(',', $raw)),
            static fn (string $value): bool => '' !== $value,
        ));
    }

    public function getFilterPattern(): ?string
    {
        $pattern = $this->stringValue('filter_pattern');
        if ('' === $pattern) {
            return null;
        }

        // Belt-and-braces: @ suppresses PHP 8.x E_WARNING for an invalid pattern;
        // try/catch covers the forward-compat case where preg_match throws.
        try {
            if (false === @preg_match($pattern, '')) {
                return null;
            }
        } catch (\Throwable) {
            return null;
        }

        return $pattern;
    }

    private function settings(): array
    {
        try {
            $settings = $this->extensionConfiguration->get('webp');

            return is_array($settings) ? $settings : [];
        } catch (
            ExtensionConfigurationExtensionNotConfiguredException
            |ExtensionConfigurationPathDoesNotExistException
        ) {
            // Both are documented @throws of ExtensionConfiguration::get().
            // Logic errors (TypeError, etc.) stay loud.
            return [];
        }
    }

    private function stringValue(string $key): string
    {
        $value = $this->settings()[$key] ?? '';

        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function boolValue(string $key): bool
    {
        $value = $this->settings()[$key] ?? false;

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
