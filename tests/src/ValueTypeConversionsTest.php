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

use Derafu\Translation\TranslatorFactory;
use Derafu\Value\Exception\TypeMismatchException;
use Derafu\Value\Exception\ValueConversionException;
use Derafu\Value\Exception\ValueException;
use Derafu\Value\Translation\ValueTranslationResourceProvider;
use Derafu\Value\ValueCaster;
use Derafu\Value\ValueType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ValueType::class)]
#[CoversClass(ValueCaster::class)]
#[CoversClass(TypeMismatchException::class)]
#[CoversClass(ValueConversionException::class)]
#[CoversClass(ValueException::class)]
#[CoversClass(ValueTranslationResourceProvider::class)]
final class ValueTypeConversionsTest extends TestCase
{
    /**
     * @return iterable<string, array{ValueType<mixed>, mixed, mixed}>
     */
    public static function conversions(): iterable
    {
        yield 'integer' => [ValueType::toInt(), '0012', 12];
        yield 'float' => [ValueType::toFloat(), '1e3', 1000.0];
        yield 'number as integer' => [ValueType::toNumber(), '12.0', 12];
        yield 'number as float' => [ValueType::toNumber(), '12.5', 12.5];
        yield 'boolean' => [ValueType::toBool(), ' Off ', false];
        yield 'string' => [ValueType::toString(), false, '0'];
    }

    /**
     * @param ValueType<mixed> $type
     */
    #[DataProvider('conversions')]
    public function testConversionsAndExplicitNullability(ValueType $type, mixed $input, mixed $expected): void
    {
        $this->assertSame($expected, $type->parse($input));
        $this->assertNull($type->nullable()->parse(null));
        $this->expectException(TypeMismatchException::class);
        $type->parse(null);
    }

    /**
     * @param ValueType<mixed> $type
     */
    #[DataProvider('conversions')]
    public function testConversionFailuresKeepTheOriginalCause(ValueType $type, mixed $input, mixed $expected): void
    {
        try {
            $type->parse([], 'response.value', 'api');
            $this->fail('Arrays cannot be converted to scalars.');
        } catch (TypeMismatchException $exception) {
            $this->assertInstanceOf(ValueException::class, $exception);
            $this->assertInstanceOf(ValueConversionException::class, $exception->getPrevious());
            $this->assertSame('The value at "response.value" of api must be of type ' . $type->name . ', array given.', $exception->getMessage());
        }
    }

    public function testConversionsComposeWithArbitraryArrayKeysAndUnions(): void
    {
        $type = ValueType::arrayOf(ValueType::toInt());
        $this->assertSame([3 => 12, 'other' => 1000], $type->parse([3 => '12.0', 'other' => '1e3']));
        $this->assertSame([], $type->parse([]));
        $union = ValueType::oneOf(ValueType::toInt(), ValueType::string());
        $this->assertSame(12, $union->parse('12.0'));
        $this->assertSame('text', $union->parse('text'));
        $this->expectException(TypeMismatchException::class);
        $type->parse('not an array');
    }

    public function testGenericResultsKeepTheirConcreteTypes(): void
    {
        $int = static fn (int $value): int => $value;
        $float = static fn (float $value): float => $value;
        $bool = static fn (bool $value): bool => $value;
        $string = static fn (string $value): string => $value;
        $this->assertSame(12, $int(ValueType::toInt()->parse('12.0')));
        $this->assertSame(2.5, $float(ValueType::toFloat()->parse('2.5')));
        $this->assertTrue($bool(ValueType::toBool()->parse('yes')));
        $this->assertSame('0', $string(ValueType::toString()->parse(false)));
    }

    public function testNumberConversionComposesAndPreservesItsGenericType(): void
    {
        $number = static fn (int|float $value): int|float => $value;
        $this->assertSame(12, $number(ValueType::toNumber()->parse(12.0)));
        $this->assertSame(12.5, $number(ValueType::toNumber()->parse('12.5')));
        $this->assertSame([12, 12.5], ValueType::listOf(ValueType::toNumber())->parse(['12.0', '12.5']));
        $this->assertSame(12.0, ValueType::number()->parse(12.0));
    }

    public function testRootErrorsHaveAValueLocationAndAreTranslatable(): void
    {
        $translator = TranslatorFactory::create(
            defaultLocale: 'es',
            fallbackLocales: ['es', 'en'],
            resourceProviders: [new ValueTranslationResourceProvider()],
        );
        $exception = ValueType::mismatch('', 'int', 'text');
        $this->assertSame('The value at "$" must be of type int, string given.', $exception->getMessage());
        $this->assertSame('El valor en "$" debe ser de tipo int, se entregó string.', $exception->trans($translator));
        $exception = ValueType::mismatch('price', 'float', [], 'request');
        $this->assertSame('El valor en "price" de request debe ser de tipo float, se entregó array.', $exception->trans($translator));
    }

    public function testNestedInvalidConversionsKeepTheirNumericPath(): void
    {
        $this->expectExceptionMessage('The value at "rows.5.price" of report must be of type float, string given.');
        ValueType::arrayOf(ValueType::mapOf(ValueType::toFloat()))->parse([5 => ['price' => 'invalid']], 'rows', 'report');
    }

    public function testNumericMapKeysAreRejectedAndArrayKeysArePreserved(): void
    {
        $this->expectException(TypeMismatchException::class);
        ValueType::map()->parse(['123' => 'value']);
    }
}
