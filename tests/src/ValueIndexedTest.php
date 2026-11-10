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
use Derafu\Value\Translation\ValueTranslationResourceProvider;
use Derafu\Value\ValueCaster;
use Derafu\Value\ValueType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Integer-keyed collections preserve row positions and compose with value types.
 */
#[CoversClass(ValueType::class)]
#[CoversClass(ValueTranslationResourceProvider::class)]
#[UsesClass(ValueCaster::class)]
final class ValueIndexedTest extends TestCase
{
    /** @return iterable<string, array{array<int, mixed>}> */
    public static function indexedArrays(): iterable
    {
        yield 'empty' => [[]];
        yield 'consecutive from zero' => [['first', 'last']];
        yield 'gaps' => [[2 => 'first', 8 => 'last']];
        yield 'negative keys and insertion order' => [[8 => 'first', -3 => 'second', 0 => 'last']];
        yield 'mixed values' => [[2 => null, 5 => false, 8 => '', 10 => ['nested' => true]]];
        yield 'integer limits' => [[PHP_INT_MAX => 'maximum', PHP_INT_MIN => 'minimum']];
        yield 'numeric string already normalized by PHP' => [['123' => 'value']];
    }

    /** @param array<int, mixed> $input */
    #[DataProvider('indexedArrays')]
    public function testKeysOrderAndValuesArePreserved(array $input): void
    {
        $this->assertSame($input, $this->indexed($input));
        $this->assertSame($input, ValueType::indexedOf(ValueType::mixed())->parse($input));
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidCollections(): iterable
    {
        yield 'null' => [null];
        yield 'string' => ['value'];
        yield 'integer' => [123];
        yield 'boolean' => [false];
        yield 'float' => [1.5];
        yield 'object' => [new stdClass()];
    }

    #[DataProvider('invalidCollections')]
    public function testNonArraysAreRejectedWithTheirSource(mixed $input): void
    {
        foreach ([ValueType::indexed(), ValueType::indexedOf(ValueType::array())] as $type) {
            try {
                $type->parse($input, 'rows', 'report.json');
                $this->fail('The collection must be an array.');
            } catch (TypeMismatchException $exception) {
                $this->assertSame(
                    'The value at "rows" of report.json must be of type ' . $type->name . ', ' . get_debug_type($input) . ' given.',
                    $exception->getMessage()
                );
            }
        }
    }

    /** @return iterable<string, array{string}> */
    public static function stringKeys(): iterable
    {
        yield 'text' => ['extra'];
        yield 'leading zero' => ['02'];
        yield 'decimal' => ['2.0'];
        yield 'explicit plus' => ['+2'];
        yield 'empty text' => [''];
    }

    #[DataProvider('stringKeys')]
    public function testStringKeysAreNotConverted(string $key): void
    {
        $input = [2 => 'first', $key => 'last'];
        foreach ([ValueType::indexed(), ValueType::indexedOf(ValueType::string())] as $type) {
            try {
                $type->parse($input, 'rows', 'report.json');
                $this->fail('String keys must not be accepted or converted.');
            } catch (TypeMismatchException $exception) {
                $this->assertSame(
                    'The value at "' . ValueType::path('rows', $key) . '" of report.json must be of type int key, string given.',
                    $exception->getMessage()
                );
                $this->assertSame([2, $key], array_keys($input));
            }
        }
    }

    public function testRowsAndTheirCellKeysArePreservedWithInferredTypes(): void
    {
        $input = [8 => ['name' => 'first', 'quantity' => 0], 2 => [null, false, 'last']];

        $this->assertSame($input, $this->rows($input));
    }

    public function testStrictItemValidationDoesNotConvertNumericStrings(): void
    {
        $this->expectException(TypeMismatchException::class);
        $this->expectExceptionMessage('The value at "rows.8" of report.json must be of type int, string given.');

        ValueType::indexedOf(ValueType::int())->parse([2 => 1, 8 => '12'], 'rows', 'report.json');
    }

    public function testExplicitValueConversionsPreserveRowKeysAndInput(): void
    {
        $input = [8 => '12.0', 2 => '1e3'];

        $this->assertSame([8 => 12, 2 => 1000], ValueType::indexedOf(ValueType::toInt())->parse($input));
        $this->assertSame([8 => '12.0', 2 => '1e3'], $input);
    }

    public function testNestedErrorsKeepThePathAndAreTranslated(): void
    {
        $type = ValueType::indexedOf(ValueType::mapOf(ValueType::int()));

        try {
            $type->parse([2 => ['quantity' => 1], 8 => ['quantity' => 'invalid']], 'rows', 'report.json');
            $this->fail('A value with the wrong type must be rejected.');
        } catch (TypeMismatchException $exception) {
            $this->assertSame(
                'The value at "rows.8.quantity" of report.json must be of type int, string given.',
                $exception->getMessage()
            );
            $translator = TranslatorFactory::create('es', resourceProviders: [new ValueTranslationResourceProvider()]);
            $this->assertSame(
                'El valor en "rows.8.quantity" de report.json debe ser de tipo int, se entregó string.',
                $exception->trans($translator)
            );
        }
    }

    public function testNullabilityAndUnionsComposeWithIndexedArrays(): void
    {
        $this->assertNull(ValueType::indexed()->nullable()->parse(null));
        $this->assertNull(ValueType::indexedOf(ValueType::int())->nullable()->parse(null));
        $this->assertSame([8 => null], ValueType::indexedOf(ValueType::int()->nullable())->parse([8 => null]));
        $type = ValueType::oneOf(ValueType::indexedOf(ValueType::int()), ValueType::string());
        $this->assertSame([8 => 1], $type->parse([8 => 1]));
        $this->assertSame('all', $type->parse('all'));
    }

    public function testObjectsArePreservedWithoutCloning(): void
    {
        $object = new stdClass();

        $this->assertSame($object, ValueType::indexed()->parse([8 => $object])[8]);
        $this->assertSame($object, ValueType::indexedOf(ValueType::instanceOf(stdClass::class))->parse([8 => $object])[8]);
    }

    public function testFactoriesDescribeTheirComposedTypes(): void
    {
        $this->assertSame('indexed', ValueType::indexed()->name);
        $this->assertSame('indexed<array>', ValueType::indexedOf(ValueType::array())->name);
        $this->assertSame('map<indexed<int>>', ValueType::mapOf(ValueType::indexedOf(ValueType::int()))->name);
    }

    /** @return array<int, mixed> */
    private function indexed(mixed $input): array
    {
        return ValueType::indexed()->parse($input);
    }

    /** @return array<int, array<array-key, mixed>> */
    private function rows(mixed $input): array
    {
        return ValueType::indexedOf(ValueType::array())->parse($input);
    }
}
