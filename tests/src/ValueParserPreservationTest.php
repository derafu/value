<?php

declare(strict_types=1);

/**
 * Derafu: Value - Typed Value Validation and Conversion.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsValue;

use Derafu\Value\ValueCaster;
use Derafu\Value\ValueParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Stringable;

#[CoversClass(ValueParser::class)]
#[CoversClass(ValueCaster::class)]
final class ValueParserPreservationTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int|float}>
     */
    public static function alteredNumericFormats(): iterable
    {
        yield 'leading zeros' => ['0012', 12];
        yield 'positive sign' => ['+12', 12];
        yield 'negative zero' => ['-0', 0];
        yield 'decimal integer' => ['12.0', 12];
        yield 'trailing fractional zero' => ['19.90', 19.9];
        yield 'missing leading zero' => ['.5', 0.5];
        yield 'empty fraction' => ['1.', 1];
        yield 'exponent' => ['1e3', 1000];
        yield 'fractional exponent' => ['1e-2', 0.01];
        yield 'exterior whitespace' => [' 12 ', 12];
        yield 'numeric one stays text instead of becoming boolean' => ['1.0', 1];
        yield 'numeric zero stays text instead of becoming boolean' => ['0.0', 0];
        yield 'overflow representation' => ['1e400', INF];
    }

    #[DataProvider('alteredNumericFormats')]
    public function testFormatPreservationKeepsOriginalNumericText(string $text, int|float $converted): void
    {
        $this->assertSame($converted, ValueParser::parse($text));
        $this->assertSame($text, ValueParser::parse($text, preserveNumericFormat: true));
        $this->assertSame($text, ValueParser::parse($text, preserveNumericFormat: true, preserveNumericPrecision: true));
    }

    /**
     * @return iterable<string, array{string, int|float}>
     */
    public static function canonicalFormats(): iterable
    {
        yield 'zero' => ['0', 0];
        yield 'one' => ['1', 1];
        yield 'integer' => ['12', 12];
        yield 'negative integer' => ['-12', -12];
        yield 'decimal' => ['0.5', 0.5];
        yield 'negative decimal' => ['-12.5', -12.5];
    }

    #[DataProvider('canonicalFormats')]
    public function testCanonicalNumericFormatsStillConvert(string $text, int|float $expected): void
    {
        $this->assertSame($expected, ValueParser::parse($text, preserveNumericFormat: true));
        $this->assertSame($expected, ValueParser::parse($text, preserveNumericFormat: true, preserveNumericPrecision: true));
    }

    /**
     * @return iterable<string, array{string, float}>
     */
    public static function lossyConversions(): iterable
    {
        yield 'fraction rounded to an integer' => ['1.0000000000000000001', 1.0];
        yield 'fraction rounded to a shorter decimal' => ['0.10000000000000001', 0.1];
        yield 'very large integer' => ['123456789012345678901234567890', 1.2345678901234568e29];
        yield 'positive infinity' => ['1e400', INF];
        yield 'negative infinity' => ['-1e400', -INF];
        yield 'underflow' => ['1e-400', 0.0];
        yield 'whitespace around unsafe input' => [' 1.0000000000000000001 ', 1.0];
    }

    #[DataProvider('lossyConversions')]
    public function testPrecisionPreservationKeepsUnsafeNumericText(string $text, float $converted): void
    {
        $this->assertSame($converted, ValueParser::parse($text));
        $this->assertSame($text, ValueParser::parse($text, preserveNumericPrecision: true));
    }

    /**
     * @return iterable<string, array{string, int|float}>
     */
    public static function preservedDecimalPrecision(): iterable
    {
        yield 'integer' => ['12', 12];
        yield 'negative integer' => ['-12', -12];
        yield 'format differs but precision does not' => ['0012', 12];
        yield 'decimal trailing zeros' => ['12.5000', 12.5];
        yield 'positive sign' => ['+12.5', 12.5];
        yield 'negative decimal' => ['-12.5', -12.5];
        yield 'negative zero' => ['-0.0', 0];
        yield 'ordinary inexact binary decimal' => ['0.1', 0.1];
        yield 'shifted decimal' => ['1e-2', 0.01];
        yield 'large exponent with preserved decimal digits' => ['1e30', 1.0e30];
        yield 'exponent formatting differs' => ['1.25E+2', 125];
        yield 'small subnormal' => ['5e-324', 5e-324];
        yield 'seventeen significant decimal digits' => ['1.2345678901234567', 1.2345678901234567];
        yield 'integral scientific input' => ['1e3', 1000];
        yield 'exterior numeric whitespace' => [" \t12.5\n", 12.5];
        yield 'numeric form feed whitespace' => ["\f12.5\f", 12.5];
        yield 'integer maximum without a float intermediate' => [(string) PHP_INT_MAX . '.0', PHP_INT_MAX];
    }

    #[DataProvider('preservedDecimalPrecision')]
    public function testPrecisionPreservationDoesNotRequireExactBinaryFractions(string $text, int|float $expected): void
    {
        $this->assertSame($expected, ValueParser::parse($text, preserveNumericPrecision: true));
    }

    /**
     * @return iterable<string, array{mixed, mixed}>
     */
    public static function otherValues(): iterable
    {
        yield 'literal true' => ['true', true];
        yield 'parenthesized false' => ['(false)', false];
        yield 'literal null' => ['(null)', null];
        yield 'literal empty' => ['empty', ''];
        yield 'boolean word' => ['yes', true];
        yield 'empty text follows boolean conversion' => ['', false];
        yield 'whitespace follows boolean conversion' => ['   ', false];
        yield 'text remains intact' => [' text ', ' text '];
        yield 'unsupported numeric grammar remains intact' => ['1_000', '1_000'];
        yield 'nil remains text' => ['nil', 'nil'];
        yield 'integer' => [12, 12];
        yield 'native float retains its type' => [12.0, 12.0];
        yield 'native infinity' => [INF, INF];
        yield 'boolean' => [false, false];
        yield 'null' => [null, null];
        yield 'array is not traversed' => [['0012', '1e400'], ['0012', '1e400']];
    }

    #[DataProvider('otherValues')]
    public function testNumericOptionsDoNotChangeOtherKindsOfValues(mixed $input, mixed $expected): void
    {
        $this->assertSame($expected, ValueParser::parse($input, preserveNumericFormat: true));
        $this->assertSame($expected, ValueParser::parse($input, preserveNumericPrecision: true));
        $this->assertSame($expected, ValueParser::parse($input, preserveNumericFormat: true, preserveNumericPrecision: true));
    }

    public function testNativeObjectsAndNanArePreserved(): void
    {
        $object = new stdClass();
        $this->assertSame($object, ValueParser::parse($object, preserveNumericFormat: true, preserveNumericPrecision: true));
        $this->assertNan(ValueParser::parse(NAN, preserveNumericFormat: true, preserveNumericPrecision: true));
    }

    public function testStringableTextUsesTheSamePreservationOptions(): void
    {
        $text = new class () implements Stringable {
            public function __toString(): string
            {
                return '0012';
            }
        };
        $this->assertSame(12, ValueParser::parse($text));
        $this->assertSame('0012', ValueParser::parse($text, preserveNumericFormat: true));
        $this->assertSame(12, ValueParser::parse($text, preserveNumericPrecision: true));
    }

    public function testCasterConversionsRemainUnrestrictedByParserOptions(): void
    {
        $this->assertSame('0012', ValueParser::parse('0012', preserveNumericFormat: true));
        $this->assertSame(12, ValueCaster::toInt('0012'));
        $this->assertSame('1e400', ValueParser::parse('1e400', preserveNumericPrecision: true));
        $this->assertSame(INF, ValueCaster::toFloat('1e400'));
    }
}
