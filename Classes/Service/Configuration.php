<?php

declare(strict_types=1);

namespace Plan2net\Webp\Service;

use Plan2net\Webp\Format\OutputFormat;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

final readonly class Configuration
{
    public function __construct(
        private ExtensionConfiguration $extensionConfiguration,
    ) {
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

    public function getFilterPattern(): ?string
    {
        $pattern = $this->stringValue('filter_pattern');
        if ('' === $pattern) {
            return null;
        }
        if (false === @preg_match($pattern, '')) {
            return null;
        }

        return $pattern;
    }

    /**
     * @return list<OutputFormat>
     */
    public function getEnabledFormats(): array
    {
        $raw = $this->stringValue('formats_enabled');
        if ('' === $raw) {
            return [OutputFormat::Webp];
        }

        $formats = [];
        foreach (\explode(',', $raw) as $token) {
            $format = OutputFormat::tryFrom(\strtolower(\trim($token)));
            if (null !== $format) {
                $formats[] = $format;
            }
        }

        return $formats;
    }

    public function isFormatRunnable(OutputFormat $format): bool
    {
        return '' !== $this->getConverterFor($format)
            && '' !== $this->getRawParameters($format);
    }

    public function getConverterFor(OutputFormat $format): string
    {
        return $this->perFormatOrLegacyWebp($format, 'converter');
    }

    public function getParametersFor(OutputFormat $format, string $mimeType): ?string
    {
        $raw = $this->getRawParameters($format);
        if ('' === $raw) {
            return null;
        }

        return self::lookupMimeType($raw, $mimeType);
    }

    public function isSupportedMimeTypeFor(OutputFormat $format, string $mimeType): bool
    {
        $list = $this->perFormatOrLegacyWebp($format, 'mime_types');
        if ('' === $list) {
            return false;
        }

        $configured = \array_map(
            static fn (string $value): string => \strtolower(\trim($value)),
            \explode(',', $list),
        );

        return \in_array(\strtolower($mimeType), $configured, true);
    }

    public function getRawParameters(OutputFormat $format): string
    {
        return $this->perFormatOrLegacyWebp($format, 'parameters');
    }

    private function perFormatOrLegacyWebp(OutputFormat $format, string $baseKey): string
    {
        $perFormat = $this->stringValue($baseKey . '_' . $format->value);
        if ('' !== $perFormat) {
            return $perFormat;
        }

        return OutputFormat::Webp === $format ? $this->stringValue($baseKey) : '';
    }

    private static function lookupMimeType(string $rawParameters, string $mimeType): ?string
    {
        foreach (\explode('|', $rawParameters) as $segment) {
            $parts = \explode('::', $segment, 2);
            $type = $parts[0] ?? null;
            $options = $parts[1] ?? null;
            if (empty($options)) {
                return $type;
            }
            if ($type === $mimeType) {
                return $options;
            }
        }

        return null;
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
