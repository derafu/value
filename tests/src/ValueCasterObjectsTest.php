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
use Countable;
use Derafu\Value\Exception\TypeMismatchException;
use Derafu\Value\Exception\ValueException;
use Derafu\Value\ValueCaster;
use Derafu\Value\ValueType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(ValueCaster::class)]
#[CoversClass(ValueType::class)]
#[CoversClass(ValueException::class)]
#[CoversClass(TypeMismatchException::class)]
final class ValueCasterObjectsTest extends TestCase
{
    public function testReadsPreserveTheInstanceOfAClassOrAnInterface(): void
    {
        $object = new ArrayObject([1]);

        $this->assertSame($object, ValueCaster::getObject($object, ArrayObject::class));
        $this->assertSame($object, ValueCaster::requireObject($object, ArrayObject::class));
        $this->assertSame($object, ValueCaster::getObject($object, Countable::class));
        $this->assertSame($object, ValueCaster::requireObject($object, Countable::class));
    }

    public function testOptionalReadPreservesNull(): void
    {
        $this->assertNull(ValueCaster::getObject(null, stdClass::class));
    }

    public function testRequiredReadRejectsNull(): void
    {
        $this->expectException(TypeMismatchException::class);
        ValueCaster::requireObject(null, stdClass::class);
    }

    public function testWrongTypesThrowInsteadOfBeingConverted(): void
    {
        foreach ([0, 'stdClass', 1.5, true, [], new ArrayObject()] as $value) {
            $reads = [
                static fn (mixed $value): ?object => ValueCaster::getObject($value, stdClass::class),
                static fn (mixed $value): object => ValueCaster::requireObject($value, stdClass::class),
            ];
            foreach ($reads as $read) {
                try {
                    $read($value);
                    $this->fail('Only an instance of the class is an object of it.');
                } catch (TypeMismatchException $exception) {
                    $this->assertSame(
                        stdClass::class,
                        $exception->getTranslatableMessage()->getParameters()['expected']
                    );
                }
            }
        }
    }
}
