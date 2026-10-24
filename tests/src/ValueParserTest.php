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

use ArrayObject;
use Derafu\Value\Exception\ValueConversionException;
use Derafu\Value\ValueCaster;
use Derafu\Value\ValueParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Stringable;

#[CoversClass(ValueParser::class)]
#[CoversClass(ValueCaster::class)]
#[CoversClass(ValueConversionException::class)]
final class ValueParserTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool|string|null}>
     */
    public static function tokens(): iterable
    {
        foreach ([
            'null' => null,
            'empty' => '',
            'true' => true,
            'false' => false,
        ] as $token => $expected) {
            foreach ([$token, '(' . $token . ')'] as $representation) {
                yield $representation => [$representation, $expected];
                yield $representation . ' uppercase' => [strtoupper($representation), $expected];
                yield $representation . ' mixed case' => [ucfirst($representation), $expected];
                yield $representation . ' exterior whitespace' => [" \t" . $representation . "\n ", $expected];
            }
        }
    }

    #[DataProvider('tokens')]
    public function testRecognizesTokensWithoutDependingOnCaseOrExteriorWhitespace(string $input, bool|string|null $expected): void
    {
        $this->assertSame($expected, ValueParser::parse($input));
    }

    #[DataProvider('tokens')]
    #[DataProvider('convertedText')]
    public function testInterpretsStringableTextWithTheSameRules(string $input, int|float|bool|string|null $expected): void
    {
        $text = new class ($input) implements Stringable {
            public int $calls = 0;

            public function __construct(private string $text)
            {
            }

            public function __toString(): string
            {
                $this->calls++;

                return $this->text;
            }
        };
        $this->assertSame($expected, ValueParser::parse($text));
        $this->assertSame(1, $text->calls);
    }

    /**
     * @return iterable<string, array{string, int|float|bool}>
     */
    public static function convertedText(): iterable
    {
        yield 'integer' => ['2', 2];
        yield 'integer zero before boolean' => ['0', 0];
        yield 'integer one before boolean' => ['1', 1];
        yield 'leading zeros' => ['0012', 12];
        yield 'explicit positive sign' => ['+12', 12];
        yield 'negative integer' => ['-12', -12];
        yield 'integral decimal before float' => ['12.0', 12];
        yield 'integral exponent before float' => ['1e3', 1000];
        yield 'integral fractional mantissa' => ['1.2e1', 12];
        yield 'numeric exterior whitespace' => [" \t2\n ", 2];
        yield 'maximum integer' => [(string) PHP_INT_MAX, PHP_INT_MAX];
        yield 'minimum integer' => [(string) PHP_INT_MIN, PHP_INT_MIN];
        yield 'maximum integer decimal' => [(string) PHP_INT_MAX . '.0', PHP_INT_MAX];
        yield 'fractional float' => ['12.5', 12.5];
        yield 'fractional exponent' => ['1e-2', 0.01];
        yield 'fraction rejected as int even if float rounds it' => ['1.0000000000000000001', 1.0];
        yield 'integer overflow falls back to float' => ['1e30', 1.0e30];
        yield 'float overflow follows caster' => ['1e400', INF];
        yield 'negative float overflow follows caster' => ['-1e400', -INF];
        yield 'float underflow follows caster' => ['1e-400', 0.0];
        yield 'yes' => ['yes', true];
        yield 'on' => ['on', true];
        yield 'no' => ['no', false];
        yield 'off' => ['off', false];
        yield 'boolean exterior whitespace and case' => [' OFF ', false];
        yield 'empty string follows boolean caster' => ['', false];
        yield 'spaces follow boolean caster' => ['   ', false];
        yield 'other whitespace follows boolean caster' => ["\t\n", false];
        yield 'numeric spelling of boolean one stays integer' => ['1.0', 1];
        yield 'numeric spelling of boolean zero stays integer' => ['0.0', 0];
    }

    #[DataProvider('convertedText')]
    public function testUsesNumericThenBooleanConversions(string $input, int|float|bool $expected): void
    {
        $this->assertSame($expected, ValueParser::parse($input));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unrecognizedText(): iterable
    {
        foreach ([' text ', 'nil', '(nil)', 'nullified', 'empty value', '( null )', '((true))', ' false text ', '1_000', '1,25', '2abc', 'INF', 'NAN'] as $index => $text) {
            yield 'text ' . $index => [$text];
        }
    }

    #[DataProvider('unrecognizedText')]
    public function testPreservesUnrecognizedTextExactly(string $text): void
    {
        $this->assertSame($text, ValueParser::parse($text));
    }

    public function testPreservesUnrecognizedStringableTextExactly(): void
    {
        $text = new class () implements Stringable {
            public function __toString(): string
            {
                return ' text ';
            }
        };
        $this->assertSame(' text ', ValueParser::parse($text));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function otherValues(): iterable
    {
        yield 'null' => [null];
        yield 'true' => [true];
        yield 'false' => [false];
        yield 'integer zero' => [0];
        yield 'integer' => [12];
        yield 'float zero' => [0.0];
        yield 'float' => [12.5];
        yield 'infinity' => [INF];
        yield 'negative infinity' => [-INF];
        yield 'empty array' => [[]];
        yield 'object' => [new stdClass()];
        yield 'countable object' => [new ArrayObject()];
    }

    #[DataProvider('otherValues')]
    public function testReturnsNonTextValuesUnchanged(mixed $value): void
    {
        $this->assertSame($value, ValueParser::parse($value));
    }

    public function testPreservesNan(): void
    {
        $this->assertNan(ValueParser::parse(NAN));
    }

    public function testDoesNotTraverseOrMutateArrays(): void
    {
        $object = new class () implements Stringable {
            public function __toString(): string
            {
                throw new RuntimeException('Nested values must not be converted.');
            }
        };
        $values = [5 => 'null', 'nested' => ['(empty)', 'true', '2', 'yes', $object]];
        $this->assertSame($values, ValueParser::parse($values));
        $this->assertSame('null', $values[5]);
    }

    public function testDoesNotConsumeOrCloseResources(): void
    {
        $stream = fopen('php://memory', 'r+');
        $this->assertIsResource($stream);
        try {
            $this->assertSame($stream, ValueParser::parse($stream));
            $this->assertSame(1, fwrite($stream, 'x'));
        } finally {
            fclose($stream);
        }
        $this->assertSame($stream, ValueParser::parse($stream));
    }

    public function testPreservesTheCauseWhenStringableConversionFails(): void
    {
        $cause = new RuntimeException('Cannot generate text.');
        $text = new class ($cause) implements Stringable {
            public function __construct(private RuntimeException $cause)
            {
            }

            public function __toString(): string
            {
                throw $this->cause;
            }
        };
        try {
            ValueParser::parse($text);
            $this->fail('String conversion must report its failure.');
        } catch (ValueConversionException $exception) {
            $this->assertSame($cause, $exception->getPrevious());
        }
    }
}
