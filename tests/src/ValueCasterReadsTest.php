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
use RuntimeException;
use stdClass;
use Stringable;

#[CoversClass(ValueCaster::class)]
#[CoversClass(ValueType::class)]
#[CoversClass(ValueException::class)]
#[CoversClass(TypeMismatchException::class)]
#[CoversClass(ValueConversionException::class)]
#[CoversClass(ValueTranslationResourceProvider::class)]
final class ValueCasterReadsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function validReads(): iterable
    {
        $inputs = [
            'String' => ['', '  text  ', '0', '12.0'],
            'Int' => [0, -1, PHP_INT_MIN, PHP_INT_MAX],
            'Float' => [0.0, -0.0, 12.0, 1.5, INF, -INF, NAN],
            'Number' => [0, 0.0, -0.0, 12, 12.0, 1.5, PHP_INT_MIN, PHP_INT_MAX, INF, -INF, NAN],
            'Bool' => [false, true],
        ];
        foreach ($inputs as $suffix => $values) {
            foreach (['get', 'require'] as $prefix) {
                foreach ($values as $index => $value) {
                    yield $prefix . $suffix . ' ' . $index => [$prefix . $suffix, $value];
                }
            }
        }
    }

    #[DataProvider('validReads')]
    public function testReadsPreserveValuesAndTheirNativeTypes(string $method, mixed $input): void
    {
        $actual = self::read($method, $input);
        if (is_float($input) && is_nan($input)) {
            $this->assertNan($actual);
        } else {
            $this->assertSame($input, $actual);
        }
        if ($input === 0.0) {
            $this->assertIsFloat($actual);
            $this->assertSame(fdiv(1.0, $input), fdiv(1.0, $actual));
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function suffixes(): iterable
    {
        foreach (['String', 'Int', 'Float', 'Number', 'Bool'] as $suffix) {
            yield $suffix => [$suffix];
        }
    }

    #[DataProvider('suffixes')]
    public function testOptionalReadsPreserveNull(string $suffix): void
    {
        $method = 'get' . $suffix;
        $this->assertNull(self::read($method, null));
    }

    #[DataProvider('suffixes')]
    public function testRequiredReadsRejectNull(string $suffix): void
    {
        $method = 'require' . $suffix;
        $this->expectException(TypeMismatchException::class);
        self::read($method, null);
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function invalidReads(): iterable
    {
        $inputs = [
            'String' => [0, 12, 12.5, INF, NAN, false, true],
            'Int' => ['12', '12.0', '1e3', 12.0, 1.5, INF, NAN, false, true],
            'Float' => [0, 12, '12.5', false, true],
            'Number' => ['12', '12.5', '1e3', false, true],
            'Bool' => [0, 1, 0.0, 1.0, 'true', 'false', '1', '0'],
        ];
        foreach ($inputs as $suffix => $values) {
            foreach (['get', 'require'] as $prefix) {
                foreach ([...$values, [], ['value'], new stdClass()] as $index => $value) {
                    yield $prefix . $suffix . ' ' . $index => [$prefix . $suffix, $value];
                }
            }
        }
    }

    #[DataProvider('invalidReads')]
    public function testWrongTypesThrowInsteadOfBeingConvertedOrReturningNull(string $method, mixed $input): void
    {
        $this->expectException(TypeMismatchException::class);
        self::read($method, $input);
    }

    public function testStringableIsAcceptedOnlyForTextAndPreservesWhitespace(): void
    {
        $value = new class () implements Stringable {
            public function __toString(): string
            {
                return ' 12 ';
            }
        };
        $this->assertSame(' 12 ', ValueCaster::getString($value));
        $this->assertSame(' 12 ', ValueCaster::requireString($value));
        foreach (['Int', 'Float', 'Number', 'Bool'] as $suffix) {
            foreach (['get', 'require'] as $prefix) {
                $method = $prefix . $suffix;
                try {
                    self::read($method, $value);
                    $this->fail('Stringable text must not become numeric or boolean input.');
                } catch (TypeMismatchException $exception) {
                    $this->assertSame(get_debug_type($value), $exception->getTranslatableMessage()->getParameters()['given']);
                }
            }
        }
    }

    public function testStringableFailuresKeepTheOriginalCause(): void
    {
        $cause = new RuntimeException('Text cannot be produced.');
        $value = new class ($cause) implements Stringable {
            public function __construct(private RuntimeException $cause)
            {
            }

            public function __toString(): string
            {
                throw $this->cause;
            }
        };
        foreach (['getString', 'requireString'] as $method) {
            try {
                self::read($method, $value);
                $this->fail('A failing Stringable must not produce a value or null.');
            } catch (TypeMismatchException $exception) {
                $conversion = $exception->getPrevious();
                $this->assertInstanceOf(ValueConversionException::class, $conversion);
                $this->assertSame($cause, $conversion->getPrevious());
            }
        }
    }

    public function testResourceInputsAreRejectedWithoutClosingThem(): void
    {
        $stream = fopen('php://memory', 'r+');
        $this->assertIsResource($stream);
        try {
            foreach (['String', 'Int', 'Float', 'Number', 'Bool'] as $suffix) {
                foreach (['get', 'require'] as $prefix) {
                    $method = $prefix . $suffix;
                    try {
                        self::read($method, $stream);
                        $this->fail('A resource is not a scalar value.');
                    } catch (TypeMismatchException) {
                        $this->assertSame(1, fwrite($stream, 'x'));
                    }
                }
            }
        } finally {
            fclose($stream);
        }
    }

    public function testStrictReadDiagnosticsRemainTranslatable(): void
    {
        $translator = TranslatorFactory::create('es', resourceProviders: [new ValueTranslationResourceProvider()]);
        try {
            ValueCaster::requireInt('12');
            $this->fail('Numeric text is not an existing integer.');
        } catch (TypeMismatchException $exception) {
            $this->assertSame('El valor en "$" debe ser de tipo int, se entregó string.', $exception->trans($translator));
        }
    }

    /**
     * @throws TypeMismatchException If the supplied value does not match the read type.
     */
    private static function read(string $method, mixed $value): mixed
    {
        return match ($method) {
            'getString' => ValueCaster::getString($value),
            'requireString' => ValueCaster::requireString($value),
            'getInt' => ValueCaster::getInt($value),
            'requireInt' => ValueCaster::requireInt($value),
            'getFloat' => ValueCaster::getFloat($value),
            'requireFloat' => ValueCaster::requireFloat($value),
            'getNumber' => ValueCaster::getNumber($value),
            'requireNumber' => ValueCaster::requireNumber($value),
            'getBool' => ValueCaster::getBool($value),
            'requireBool' => ValueCaster::requireBool($value),
            default => throw new RuntimeException('Unknown read method in test data.'),
        };
    }
}
