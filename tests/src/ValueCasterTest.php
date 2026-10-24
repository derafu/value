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

use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException;
use Derafu\Translation\TranslatorFactory;
use Derafu\Value\Exception\ValueConversionException;
use Derafu\Value\Translation\ValueTranslationResourceProvider;
use Derafu\Value\ValueCaster;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Stringable;

#[CoversClass(ValueCaster::class)]
#[CoversClass(ValueConversionException::class)]
#[CoversClass(ValueTranslationResourceProvider::class)]
final class ValueCasterTest extends TestCase
{
    /**
     * @return iterable<string, array{string, mixed, mixed}>
     */
    public static function validConversions(): iterable
    {
        foreach (['toInt', 'toFloat', 'toNumber', 'toBool', 'toString'] as $method) {
            yield $method . ' preserves null' => [$method, null, null];
        }
        foreach ([
            [12, 12], [12.0, 12], ['12', 12], ['12.0', 12], ['0012', 12],
            ['+0012', 12], ['1e3', 1000], ['1.2e1', 12], [' 12 ', 12],
            ["\f12\f", 12], ['-.12E+2', -12], ['12.', 12],
            ['1200e-2', 12], ['-1200e-2', -12], ['000.000', 0],
            ['0e999999999999999999999999999999999', 0],
            ['-0e-99999999999999999999999999999999', 0],
            ['1e+000000000000000000000000000003', 1000],
            ['120000000000000000000000e-22', 12],
            [PHP_INT_MAX, PHP_INT_MAX], [PHP_INT_MIN, PHP_INT_MIN],
            [(string) PHP_INT_MAX, PHP_INT_MAX], [(string) PHP_INT_MIN, PHP_INT_MIN],
            [(string) PHP_INT_MAX . '.000', PHP_INT_MAX],
            [(string) PHP_INT_MIN . '0e-1', PHP_INT_MIN],
            [(float) PHP_INT_MIN, PHP_INT_MIN], [0.0, 0], [-0.0, 0],
        ] as $index => [$input, $expected]) {
            yield 'integer ' . $index => ['toInt', $input, $expected];
        }
        foreach ([
            [12, 12.0], [12.5, 12.5], ['12', 12.0], ['0012', 12.0],
            ['12.5', 12.5], [' 12.5 ', 12.5], ['1e3', 1000.0],
            ['1e400', INF], ['-1e400', -INF], ['1e-400', 0.0],
            [INF, INF], [-INF, -INF], ['-0.0', -0.0],
            ['.5', 0.5], ['12.', 12.0],
        ] as $index => [$input, $expected]) {
            yield 'float ' . $index => ['toFloat', $input, $expected];
        }
        foreach ([
            [12, 12], [12.0, 12], ['12', 12], ['0012', 12],
            ['12.0', 12], ['1e3', 1000], [' 12 ', 12],
            [12.5, 12.5], ['12.5', 12.5], ['1e-2', 0.01],
            [PHP_INT_MAX, PHP_INT_MAX], [PHP_INT_MIN, PHP_INT_MIN],
            [(string) PHP_INT_MAX . '.0', PHP_INT_MAX],
            ['1.0000000000000000001', 1.0],
            ['1e30', 1.0e30], ['1e400', INF], ['-1e400', -INF],
            ['1e-400', 0.0], [INF, INF], [-INF, -INF],
        ] as $index => [$input, $expected]) {
            yield 'number ' . $index => ['toNumber', $input, $expected];
        }
        foreach ([true, 1, 1.0, '1', 'true', 'yes', 'on', ' TRUE ', 'YeS'] as $index => $input) {
            yield 'true ' . $index => ['toBool', $input, true];
        }
        foreach ([false, 0, 0.0, -0.0, '0', 'false', 'no', 'off', '', ' Off ', '   '] as $index => $input) {
            yield 'false ' . $index => ['toBool', $input, false];
        }
        foreach ([
            [' text ', ' text '], ['', ''], [12, '12'], [12.5, '12.5'],
            [true, '1'], [false, '0'], [INF, 'INF'], [-INF, '-INF'], [NAN, 'NAN'],
        ] as $index => [$input, $expected]) {
            yield 'string ' . $index => ['toString', $input, $expected];
        }
    }

    #[DataProvider('validConversions')]
    public function testConvertsSupportedValues(string $method, mixed $input, mixed $expected): void
    {
        $this->assertSame($expected, self::convert($method, $input));
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function invalidConversions(): iterable
    {
        foreach (['toInt', 'toFloat', 'toNumber', 'toBool', 'toString'] as $method) {
            yield $method . ' rejects array' => [$method, []];
            yield $method . ' rejects plain object' => [$method, new stdClass()];
        }
        foreach ([
            12.5, '12.5', '1.0000000000000000001', '1e400', '1e-400',
            '12abc', '', INF, -INF, NAN, true, false,
            (string) PHP_INT_MAX . '0', (string) PHP_INT_MIN . '0',
            '999999999999999999999999999999999999',
            '1e999999999999999999999999999999999',
            '1e-99999999999999999999999999999999',
            '1e-99', '1e99', '1e20', (float) PHP_INT_MAX,
            -(float) PHP_INT_MIN * 2, '0x10', '1_000', '1,000',
        ] as $index => $input) {
            yield 'invalid integer ' . $index => ['toInt', $input];
        }
        foreach (['12abc', '1,25', '', 'INF', 'NAN', true, false, '0x10', '1_000'] as $index => $input) {
            yield 'invalid float ' . $index => ['toFloat', $input];
            yield 'invalid number ' . $index => ['toNumber', $input];
        }
        foreach ([2, -1, 0.5, INF, NAN, 'banana', '1.0', '2'] as $index => $input) {
            yield 'invalid boolean ' . $index => ['toBool', $input];
        }
    }

    #[DataProvider('invalidConversions')]
    public function testRejectsInvalidConversions(string $method, mixed $input): void
    {
        $this->expectException(ValueConversionException::class);
        self::convert($method, $input);
    }

    public function testRejectsResourcesWithoutClosingThem(): void
    {
        $stream = fopen('php://memory', 'r+');
        $this->assertIsResource($stream);
        try {
            foreach (['toInt', 'toFloat', 'toNumber', 'toBool', 'toString'] as $method) {
                try {
                    self::convert($method, $stream);
                    $this->fail('Resources cannot be converted.');
                } catch (ValueConversionException) {
                    $this->assertSame(1, fwrite($stream, 'x'));
                }
            }
        } finally {
            fclose($stream);
        }
    }

    public function testPreservesNativeNanAsFloat(): void
    {
        $this->assertNan(ValueCaster::toFloat(NAN));
        $this->assertNan(ValueCaster::toNumber(NAN));
    }

    public function testConvertsStringableObjectsOnlyToText(): void
    {
        $text = new class () implements Stringable {
            public function __toString(): string
            {
                return ' 12 ';
            }
        };
        $this->assertSame(' 12 ', ValueCaster::toString($text));
        foreach (['toInt', 'toFloat', 'toNumber', 'toBool'] as $method) {
            try {
                self::convert($method, $text);
                $this->fail('Text objects are not numeric or boolean input.');
            } catch (ValueConversionException $exception) {
                $this->assertStringContainsString('cannot be converted', $exception->getMessage());
            }
        }
    }

    public function testWrapsStringableFailuresAndPreservesTheCause(): void
    {
        $cause = new RuntimeException('Text cannot be generated.');
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
            ValueCaster::toString($text);
            $this->fail('String conversion must fail.');
        } catch (ValueConversionException $exception) {
            $this->assertSame($cause, $exception->getPrevious());
        }
    }

    /**
     * @throws ValueConversionException If the input cannot be converted.
     */
    private static function convert(string $method, mixed $value): mixed
    {
        return match ($method) {
            'toInt' => ValueCaster::toInt($value),
            'toFloat' => ValueCaster::toFloat($value),
            'toNumber' => ValueCaster::toNumber($value),
            'toBool' => ValueCaster::toBool($value),
            'toString' => ValueCaster::toString($value),
            default => throw new RuntimeException('Unknown conversion method in test data.'),
        };
    }

    public function testNumberPropagatesTheFinalConversionFailure(): void
    {
        try {
            ValueCaster::toNumber('not a number');
            $this->fail('Invalid numeric input must fail.');
        } catch (ValueConversionException $exception) {
            $this->assertSame('A value of type string cannot be converted to float.', $exception->getMessage());
            $this->assertNull($exception->getPrevious());
        }
    }

    public function testConversionExceptionIsIndependentAndTranslatable(): void
    {
        $translator = TranslatorFactory::create(
            defaultLocale: 'es',
            fallbackLocales: ['es', 'en'],
            resourceProviders: [new ValueTranslationResourceProvider()],
        );
        try {
            ValueCaster::toInt('incorrect');
            $this->fail('Invalid numeric text must be rejected.');
        } catch (ValueConversionException $exception) {
            $this->assertInstanceOf(TranslatableInvalidArgumentException::class, $exception);
            $this->assertSame('Un valor de tipo string no se puede convertir a int.', $exception->trans($translator));
        }
    }
}
