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
use BackedEnum;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Derafu\TestsValue\Fixture\ClassStringAutoloadTarget;
use Derafu\TestsValue\Fixture\EmptyTextStatus;
use Derafu\TestsValue\Fixture\PureStatus;
use Derafu\TestsValue\Fixture\TextStatus;
use Derafu\Translation\Trait\TranslatableExceptionTrait;
use Derafu\Translation\TranslatorFactory;
use Derafu\Value\Exception\TypeMismatchException;
use Derafu\Value\Translation\ValueTranslationResourceProvider;
use Derafu\Value\ValueType;
use Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use ReflectionFunctionAbstract;
use RuntimeException;
use stdClass;
use Stringable;

require_once __DIR__ . '/../fixtures/backed-enums.php';

#[CoversClass(ValueType::class)]
#[CoversClass(ValueTranslationResourceProvider::class)]
final class ValueClassStringTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function acceptedNames(): iterable
    {
        yield 'ordinary class' => [stdClass::class];
        yield 'abstract class' => [ReflectionFunctionAbstract::class];
        yield 'backed enum' => [TextStatus::class];
        yield 'empty backed enum' => [EmptyTextStatus::class];
        yield 'pure enum' => [PureStatus::class];
        yield 'leading namespace separator' => ['\\' . DateTimeImmutable::class];
        yield 'case is preserved' => ['datetimeimmutable'];
    }

    #[DataProvider('acceptedNames')]
    public function testExistingClassNamesArePreserved(string $value): void
    {
        $type = ValueType::classString();

        $this->assertSame('class-string', $type->name);
        $this->assertSame($value, $type->parse($value));
    }

    /** @return iterable<string, array{mixed}> */
    public static function rejectedNames(): iterable
    {
        yield 'null' => [null];
        yield 'integer' => [12];
        yield 'float' => [1.5];
        yield 'boolean' => [false];
        yield 'array' => [[stdClass::class]];
        yield 'object' => [new stdClass()];
        yield 'empty text' => [''];
        yield 'padded name' => [' stdClass '];
        yield 'missing class' => ['Derafu\\TestsValue\\MissingClassStringTarget'];
        yield 'interface' => [DateTimeInterface::class];
        yield 'trait' => [TranslatableExceptionTrait::class];
        yield 'scalar type name' => ['string'];
        yield 'stringable is not converted' => [new class () implements Stringable {
            public function __toString(): string
            {
                return stdClass::class;
            }
        }];
    }

    #[DataProvider('rejectedNames')]
    public function testInvalidClassNamesAreRejectedWithoutConversion(mixed $value): void
    {
        $this->expectException(TypeMismatchException::class);
        $this->expectExceptionMessage(
            'The value at "provider" of services.php must be of type class-string, ' . get_debug_type($value) . ' given.'
        );

        ValueType::classString()->parse($value, 'provider', 'services.php');
    }

    public function testResourcesAreRejectedAndRemainOpen(): void
    {
        $resource = fopen('php://memory', 'r+');
        $this->assertIsResource($resource);

        try {
            try {
                ValueType::classString()->parse($resource);
                $this->fail('A resource is not a class name.');
            } catch (TypeMismatchException $exception) {
                $this->assertSame('The value at "$" must be of type class-string, resource (stream) given.', $exception->getMessage());
                $this->assertSame(4, fwrite($resource, 'open'));
            }
        } finally {
            fclose($resource);
        }
    }

    public function testClassConstraintsAcceptTheBaseClassAndItsSubclasses(): void
    {
        $type = ValueType::classString(Exception::class);

        $this->assertSame('class-string<Exception>', $type->name);
        $this->assertSame(Exception::class, $type->parse(Exception::class));
        $this->assertSame(RuntimeException::class, $type->parse(RuntimeException::class));
    }

    public function testInterfaceConstraintsAcceptImplementingClasses(): void
    {
        $type = ValueType::classString(DateTimeInterface::class);

        $this->assertSame(DateTimeImmutable::class, $type->parse(DateTimeImmutable::class));
        $this->assertSame(DateTime::class, $type->parse(DateTime::class));
    }

    public function testBackedEnumClassValidationPreservesTheGenericType(): void
    {
        $enumClass = $this->enumClass(TextStatus::class);

        $this->assertSame(TextStatus::cases(), $enumClass::cases());
        $this->assertSame([], $this->enumClass(EmptyTextStatus::class)::cases());
    }

    /** @return class-string<BackedEnum> */
    private function enumClass(mixed $value): string
    {
        return ValueType::classString(BackedEnum::class)->parse($value);
    }

    public function testAnUnrelatedClassReportsItsConstraintAndTranslatedContext(): void
    {
        try {
            ValueType::classString(BackedEnum::class)->parse(PureStatus::class, 'source.enum', 'repositories.php');
            $this->fail('A pure enum does not implement BackedEnum.');
        } catch (TypeMismatchException $exception) {
            $this->assertSame(
                'The value at "source.enum" of repositories.php must be of type class-string<BackedEnum>, string given.',
                $exception->getMessage()
            );
            $translator = TranslatorFactory::create('es', resourceProviders: [new ValueTranslationResourceProvider()]);
            $this->assertSame(
                'El valor en "source.enum" de repositories.php debe ser de tipo class-string<BackedEnum>, se entregó string.',
                $exception->trans($translator)
            );
        }
    }

    #[RunInSeparateProcess]
    public function testClassNamesAreAutoloadedWithoutInstantiatingTheClass(): void
    {
        $class = ClassStringAutoloadTarget::class;
        $this->assertFalse(class_exists($class, false));

        $this->assertSame($class, ValueType::classString(ArrayObject::class)->parse($class));
        $this->assertTrue(class_exists($class, false));
    }

    public function testClassNamesComposeWithMapsListsAndNullability(): void
    {
        $type = ValueType::mapOf(ValueType::listOf(ValueType::classString(BackedEnum::class)));
        $value = ['catalogues' => [TextStatus::class, EmptyTextStatus::class]];

        $this->assertSame($value, $type->parse($value));
        $this->assertNull(ValueType::classString()->nullable()->parse(null));

        $this->expectException(TypeMismatchException::class);
        $this->expectExceptionMessage('The value at "catalogues.1" of config.php must be of type class-string<BackedEnum>, string given.');
        $type->parse(['catalogues' => [TextStatus::class, stdClass::class]], source: 'config.php');
    }
}
