<?php

declare(strict_types=1);

namespace Plan2net\Webp\Tests\Unit\Converter;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Plan2net\Webp\Converter\VipsOptionParser;

final class VipsOptionParserTest extends TestCase
{
    #[Test]
    #[DataProvider('parseProvider')]
    public function testParse(string $parameters, array $expected, string $description): void
    {
        self::assertSame($expected, VipsOptionParser::parse($parameters), $description);
    }

    public static function parseProvider(): array
    {
        return [
            'empty string' => [
                '',
                [],
                'Empty input yields empty array',
            ],
            'single int' => [
                'Q=85',
                ['Q' => 85],
                'Single integer value is coerced to int',
            ],
            'bool true lowercase' => [
                'lossless=true',
                ['lossless' => true],
                'Lowercase "true" coerces to bool true',
            ],
            'bool false uppercase' => [
                'lossless=FALSE',
                ['lossless' => false],
                'Uppercase "FALSE" coerces to bool false (case-insensitive)',
            ],
            'bool mixed case' => [
                'lossless=True',
                ['lossless' => true],
                'Mixed-case "True" coerces to bool true',
            ],
            'int zero' => [
                'effort=0',
                ['effort' => 0],
                'Zero is an int, not falsy string',
            ],
            'int negative' => [
                'level=-1',
                ['level' => -1],
                'Negative integers parse to int',
            ],
            'float positive' => [
                'near_lossless=0.5',
                ['near_lossless' => 0.5],
                'Positive float with decimal point parses to float',
            ],
            'plain integer-looking value stays int' => [
                'effort=1',
                ['effort' => 1],
                'A value like "1" must stay int, not become float',
            ],
            'string fallback simple' => [
                'preset=picture',
                ['preset' => 'picture'],
                'Unrecognized non-numeric value stays string',
            ],
            'string with dash' => [
                'preset=text-with-dash',
                ['preset' => 'text-with-dash'],
                'Dashes inside values do not change the type',
            ],
            'multiple spaces' => [
                'Q=85   effort=4',
                ['Q' => 85, 'effort' => 4],
                'Multiple spaces collapse to single separators',
            ],
            'tabs and newlines' => [
                "Q=85\teffort=4\nlossless=true",
                ['Q' => 85, 'effort' => 4, 'lossless' => true],
                'Any whitespace (tabs/newlines) separates tokens',
            ],
            'leading and trailing whitespace' => [
                '   Q=85 effort=4   ',
                ['Q' => 85, 'effort' => 4],
                'Outer whitespace is ignored',
            ],
            'malformed: empty key' => [
                '=85',
                [],
                'Token with no key before "=" is dropped silently',
            ],
            'malformed: empty value' => [
                'Q=',
                [],
                'Token with no value after "=" is dropped silently',
            ],
            'malformed: lone key' => [
                'Q',
                [],
                'Token without "=" is dropped silently',
            ],
            'malformed: only spaces' => [
                '       ',
                [],
                'Whitespace-only input yields empty array',
            ],
            'mixed types in one string' => [
                'Q=85 lossless=true effort=4 preset=picture',
                ['Q' => 85, 'lossless' => true, 'effort' => 4, 'preset' => 'picture'],
                'Mixed-type tokens all parse to correct types in one pass',
            ],
            'duplicate keys: last wins' => [
                'Q=80 Q=85',
                ['Q' => 85],
                'Later duplicate keys overwrite earlier ones',
            ],
            'value with embedded equals' => [
                'preset=key=value',
                ['preset' => 'key=value'],
                'Only the first "=" splits key/value; later "=" stays in the value',
            ],
        ];
    }
}
