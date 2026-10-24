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
use Derafu\Value\ValueInspector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Stringable;

#[CoversClass(ValueInspector::class)]
final class ValueInspectorTest extends TestCase
{
    /**
     * @return iterable<string, array{mixed, bool}>
     */
    public static function values(): iterable
    {
        yield 'null' => [null, true];
        yield 'empty string' => ['', true];
        yield 'empty array' => [[], true];
        yield 'false' => [false, false];
        yield 'true' => [true, false];
        yield 'integer zero' => [0, false];
        yield 'float zero' => [0.0, false];
        yield 'text zero' => ['0', false];
        yield 'negative number' => [-1, false];
        yield 'nan' => [NAN, false];
        yield 'infinity' => [INF, false];
        yield 'space' => [' ', false];
        yield 'whitespace' => [" \t\n", false];
        yield 'text null' => ['null', false];
        yield 'text' => ['content', false];
        yield 'array containing null' => [[null], false];
        yield 'array containing empty string' => [[''], false];
        yield 'nested empty array' => [[[]], false];
        yield 'plain object' => [new stdClass(), false];
        yield 'empty countable' => [new ArrayObject(), false];
    }

    #[DataProvider('values')]
    public function testOnlyNullEmptyStringAndEmptyArrayAreBlank(mixed $value, bool $expected): void
    {
        $this->assertSame($expected, ValueInspector::isBlank($value));
    }

    public function testDoesNotStringifyObjects(): void
    {
        $value = new class () implements Stringable {
            public function __toString(): string
            {
                throw new RuntimeException('Inspection must not convert objects.');
            }
        };
        $this->assertFalse(ValueInspector::isBlank($value));
    }

    public function testDoesNotConsumeOrCloseResources(): void
    {
        $stream = fopen('php://memory', 'r+');
        $this->assertIsResource($stream);
        try {
            $this->assertFalse(ValueInspector::isBlank($stream));
            $this->assertSame(1, fwrite($stream, 'x'));
        } finally {
            fclose($stream);
        }
        $this->assertFalse(ValueInspector::isBlank($stream));
    }
}
