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

use BackedEnum;
use Derafu\TestsValue\Fixture\EmptyNumberStatus;
use Derafu\TestsValue\Fixture\EmptyTextStatus;
use Derafu\TestsValue\Fixture\NumberStatus;
use Derafu\TestsValue\Fixture\OtherNumberStatus;
use Derafu\TestsValue\Fixture\OtherTextStatus;
use Derafu\TestsValue\Fixture\PureStatus;
use Derafu\TestsValue\Fixture\TextStatus;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException;
use Derafu\Translation\TranslatorFactory;
use Derafu\Value\Translation\ValueTranslationResourceProvider;
use Derafu\Value\ValueType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use UnitEnum;

require_once __DIR__ . '/../fixtures/backed-enums.php';

#[CoversClass(ValueType::class)]
#[CoversClass(ValueTranslationResourceProvider::class)]
final class ValueBackedEnumTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string<BackedEnum>, mixed, BackedEnum}>
     */
    public static function acceptedValues(): iterable
    {
        yield 'string backing' => [TextStatus::class, 'approved', TextStatus::Approved];
        yield 'numeric string backing' => [TextStatus::class, '1', TextStatus::Numeric];
        yield 'empty string backing' => [TextStatus::class, '', TextStatus::Blank];
        yield 'integer backing' => [NumberStatus::class, 1, NumberStatus::Approved];
        yield 'zero backing' => [NumberStatus::class, 0, NumberStatus::Pending];
        yield 'negative backing' => [NumberStatus::class, -1, NumberStatus::Negative];
        yield 'string enum instance' => [TextStatus::class, TextStatus::Pending, TextStatus::Pending];
        yield 'integer enum instance' => [NumberStatus::class, NumberStatus::Approved, NumberStatus::Approved];
    }

    /**
     * @param class-string<BackedEnum> $class
     */
    #[DataProvider('acceptedValues')]
    public function testCasesAndExactBackingValuesAreResolved(string $class, mixed $value, BackedEnum $expected): void
    {
        $type = ValueType::backedEnum($class);
        $this->assertSame($class, $type->name);
        $this->assertSame($expected, $type->parse($value, 'status'));
    }

    /**
     * @return iterable<string, array{class-string<BackedEnum>, mixed}>
     */
    public static function rejectedValues(): iterable
    {
        yield 'unknown string' => [TextStatus::class, 'rejected'];
        yield 'unknown integer' => [NumberStatus::class, 99];
        yield 'numeric string is not integer' => [NumberStatus::class, '1'];
        yield 'integer is not string' => [TextStatus::class, 1];
        yield 'float is not integer' => [NumberStatus::class, 1.0];
        yield 'boolean is not zero' => [NumberStatus::class, false];
        yield 'boolean is not one' => [NumberStatus::class, true];
        yield 'null' => [TextStatus::class, null];
        yield 'array' => [TextStatus::class, ['approved']];
        yield 'object' => [TextStatus::class, new stdClass()];
        yield 'another string enum' => [TextStatus::class, OtherTextStatus::Approved];
        yield 'another integer enum' => [NumberStatus::class, OtherNumberStatus::Approved];
        yield 'pure enum value' => [TextStatus::class, PureStatus::Approved];
        yield 'empty string enum has no case' => [EmptyTextStatus::class, 'value'];
        yield 'empty integer enum has no case' => [EmptyNumberStatus::class, 0];
    }

    /**
     * @param class-string<BackedEnum> $class
     */
    #[DataProvider('rejectedValues')]
    public function testInvalidInputsUseTheValueExceptionWithContext(string $class, mixed $value): void
    {
        $type = ValueType::backedEnum($class);

        $this->expectException(TranslatableInvalidArgumentException::class);
        $this->expectExceptionMessage('The value at "payload.status" of order.json must be of type ' . $class . ', ' . get_debug_type($value) . ' given.');

        $type->parse($value, 'payload.status', 'order.json');
    }

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function invalidClasses(): iterable
    {
        yield 'ordinary class' => [stdClass::class];
        yield 'pure enum' => [PureStatus::class];
        yield 'backed enum interface' => [BackedEnum::class];
        yield 'unit enum interface' => [UnitEnum::class];
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('invalidClasses')]
    public function testFactoryRejectsClassesThatAreNotBackedEnums(string $class): void
    {
        $this->expectException(TranslatableInvalidArgumentException::class);
        $this->expectExceptionMessage('The class ' . $class . ' must be a backed enum.');

        ValueType::backedEnum($class);
    }

    public function testEnumsComposeWithEveryCollectionKind(): void
    {
        $type = ValueType::backedEnum(TextStatus::class);
        $this->assertSame([TextStatus::Approved, TextStatus::Pending], ValueType::listOf($type)->parse(['approved', 'pending']));
        $this->assertSame(['first' => TextStatus::Approved], ValueType::mapOf($type)->parse(['first' => 'approved']));
        $this->assertSame([3 => TextStatus::Approved], ValueType::arrayOf($type)->parse([3 => 'approved']));
    }

    public function testNestedCollectionErrorsKeepTheirPathAndSource(): void
    {
        $this->expectExceptionMessage('The value at "payload.statuses.8" of order.json must be of type ' . TextStatus::class . ', string given.');
        ValueType::arrayOf(ValueType::backedEnum(TextStatus::class))->parse([8 => 'invalid'], 'payload.statuses', 'order.json');
    }

    public function testNullableAndUnionTypesKeepTheirContracts(): void
    {
        $type = ValueType::backedEnum(TextStatus::class);
        $this->assertNull($type->nullable()->parse(null));
        $this->assertSame(TextStatus::Approved, $type->nullable()->parse('approved'));

        $union = ValueType::oneOf($type, ValueType::literal(false));
        $this->assertFalse($union->parse(false));
        $this->assertSame(TextStatus::Pending, $union->parse('pending'));
    }

    public function testTheConcreteEnumTypeIsPreservedForConsumers(): void
    {
        $type = ValueType::backedEnum(TextStatus::class);
        $readStatus = static fn (TextStatus $status): string => $status->value;

        $this->assertSame('approved', $readStatus($type->parse('approved')));
    }

    public function testInvalidEnumClassMessagesAreTranslated(): void
    {
        try {
            ValueType::backedEnum(stdClass::class);
            $this->fail('An ordinary class cannot define a backed enum type.');
        } catch (TranslatableInvalidArgumentException $exception) {
            $translator = TranslatorFactory::create(
                defaultLocale: 'es',
                fallbackLocales: ['es', 'en'],
                resourceProviders: [new ValueTranslationResourceProvider()],
            );
            $this->assertSame('La clase stdClass debe ser un enum respaldado.', $exception->trans($translator));
        }
    }
}
