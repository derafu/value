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

use DateTimeImmutable;
use DateTimeInterface;
use Derafu\Translation\Contract\TranslatableInterface;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException;
use Derafu\Value\ValueType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(ValueType::class)]
final class ValueTypeTest extends TestCase
{
    /**
     * @return array<string, array{ValueType<mixed>, mixed}>
     */
    public static function acceptedProvider(): array
    {
        return [
            'mixed' => [ValueType::mixed(), new stdClass()],
            'string' => [ValueType::string(), 'a'],
            'int' => [ValueType::int(), 3],
            'float' => [ValueType::float(), 1.5],
            'number with an int' => [ValueType::number(), 3],
            'number with a float' => [ValueType::number(), 1.5],
            'bool' => [ValueType::bool(), false],
            'scalar' => [ValueType::scalar(), 'a'],
            'array' => [ValueType::array(), [1, 'a' => 2]],
            'map' => [ValueType::map(), ['a' => 1]],
            'empty map' => [ValueType::map(), []],
            'list' => [ValueType::list(), ['a', 1]],
            'empty list' => [ValueType::list(), []],
            'list of strings' => [ValueType::listOf(ValueType::string()), ['a', 'b']],
            'empty list of strings' => [ValueType::listOf(ValueType::string()), []],
            'map of ints' => [ValueType::mapOf(ValueType::int()), ['a' => 1]],
            'empty map of ints' => [ValueType::mapOf(ValueType::int()), []],
            'one of, first' => [ValueType::oneOf(ValueType::bool(), ValueType::map()), true],
            'one of, last' => [ValueType::oneOf(ValueType::bool(), ValueType::map()), ['a' => 1]],
            'nullable with a value' => [ValueType::string()->nullable(), 'a'],
            'nullable with null' => [ValueType::string()->nullable(), null],
            'non-empty string' => [ValueType::nonEmptyString(), 'a'],
            'literal text' => [ValueType::literal('asc', 'desc'), 'desc'],
            'literal false' => [ValueType::literal(false), false],
            'literal int' => [ValueType::literal(1, 2), 2],
            'instance of a class' => [ValueType::instanceOf(stdClass::class), new stdClass()],
            'date that is an object' => [ValueType::dateTime(), new DateTimeImmutable('2026-01-02')],
        ];
    }

    /**
     * @param ValueType<mixed> $type
     */
    #[Test]
    #[DataProvider('acceptedProvider')]
    public function shouldReturnTheValueThatIsOfTheType(ValueType $type, mixed $value): void
    {
        $this->assertSame($value, $type->parse($value, 'key'));
    }

    /**
     * @return array<string, array{ValueType<mixed>, mixed, string}>
     */
    public static function rejectedProvider(): array
    {
        return [
            'string' => [ValueType::string(), 1, 'string'],
            'int with a numeric string' => [ValueType::int(), '3', 'int'],
            'int with a float' => [ValueType::int(), 3.0, 'int'],
            'float with an int' => [ValueType::float(), 3, 'float'],
            'number' => [ValueType::number(), '3', 'float|int'],
            'bool' => [ValueType::bool(), 0, 'bool'],
            'scalar' => [ValueType::scalar(), [], 'bool|float|int|string'],
            'array' => [ValueType::array(), 'a', 'array'],
            'map with integer keys' => [ValueType::map(), [1 => 'a'], 'map'],
            'typed map with integer keys' => [ValueType::mapOf(ValueType::string()), [1 => 'a'], 'map<string>'],
            'map with a string' => [ValueType::map(), 'a', 'map'],
            'map with a list' => [ValueType::map(), ['a', 'b'], 'map'],
            'list with a string' => [ValueType::list(), 'a', 'list'],
            'list with a map' => [ValueType::list(), ['a' => 1], 'list'],
            'list of strings with a string' => [ValueType::listOf(ValueType::string()), 'a', 'list<string>'],
            'list of strings with a map' => [ValueType::listOf(ValueType::string()), ['a' => 'b'], 'list<string>'],
            'map of ints with a string' => [ValueType::mapOf(ValueType::int()), 'a', 'map<int>'],
            'map of ints with a list' => [ValueType::mapOf(ValueType::int()), [1, 2], 'map<int>'],
            'one of' => [ValueType::oneOf(ValueType::bool(), ValueType::map()), 'a', 'bool|map'],
            'nullable' => [ValueType::string()->nullable(), 1, 'string'],
            'non-empty string with an empty one' => [ValueType::nonEmptyString(), '', 'non-empty-string'],
            'non-empty string with spaces' => [ValueType::nonEmptyString(), '  ', 'non-empty-string'],
            'non-empty string with an int' => [ValueType::nonEmptyString(), 1, 'non-empty-string'],
            'literal text with another text' => [ValueType::literal('asc', 'desc'), 'up', "'asc'|'desc'"],
            'literal false with 0' => [ValueType::literal(false), 0, 'false'],
            'literal int with a numeric string' => [ValueType::literal(1, 2), '1', '1|2'],
            'instance of a class with a string' => [ValueType::instanceOf(stdClass::class), 'a', 'stdClass'],
            'instance of a class with another object' => [ValueType::instanceOf(stdClass::class), new DateTimeImmutable(), 'stdClass'],
            'date with a text that is not a date' => [ValueType::dateTime(), 'not a date', 'date'],
            'date with an empty text' => [ValueType::dateTime(), '  ', 'date'],
            'date with a float' => [ValueType::dateTime(), 1.5, 'date'],
            'date with an array' => [ValueType::dateTime(), [], 'date'],
        ];
    }

    /**
     * @param ValueType<mixed> $type
     */
    #[Test]
    #[DataProvider('rejectedProvider')]
    public function shouldRejectTheValueThatIsNotOfTheType(ValueType $type, mixed $value, string $expected): void
    {
        try {
            $type->parse($value, 'key');
            $this->fail('The value should be rejected.');
        } catch (TranslatableInvalidArgumentException $e) {
            $this->assertStringContainsString(
                'The value at "key" must be of type ' . $expected . ', ',
                $e->getMessage()
            );
        }
    }

    #[Test]
    public function shouldCreateEveryType(): void
    {
        $this->assertSame(
            ['mixed', 'string', 'int', 'float', 'float|int', 'bool', 'bool|float|int|string', 'array', 'map', 'list'],
            array_map(static fn (ValueType $t): string => $t->name, [
                ValueType::mixed(),
                ValueType::string(),
                ValueType::int(),
                ValueType::float(),
                ValueType::number(),
                ValueType::bool(),
                ValueType::scalar(),
                ValueType::array(),
                ValueType::map(),
                ValueType::list(),
            ])
        );
    }

    #[Test]
    public function shouldConvertADateFromATimestampOrAText(): void
    {
        $fromInt = ValueType::dateTime()->parse(1768435200, 'key');
        $fromText = ValueType::dateTime()->parse('2026-01-15', 'key');

        $this->assertInstanceOf(DateTimeInterface::class, $fromInt);
        $this->assertSame(1768435200, $fromInt->getTimestamp());
        $this->assertSame('2026-01-15', $fromText->format('Y-m-d'));
    }

    #[Test]
    public function shouldKeepTheLiteralFalseApartFromTheOtherTypes(): void
    {
        $type = ValueType::oneOf(ValueType::string(), ValueType::literal(false));

        $this->assertSame('a.png', $type->parse('a.png', 'image'));
        $this->assertFalse($type->parse(false, 'image'));

        $this->expectException(TranslatableInvalidArgumentException::class);
        $this->expectExceptionMessage('The value at "image" must be of type string|false, bool given.');

        $type->parse(true, 'image');
    }

    #[Test]
    public function shouldSayWhereTheValueComesFromInEveryError(): void
    {
        $types = [
            ValueType::string(),
            ValueType::listOf(ValueType::string()),
            ValueType::mapOf(ValueType::int()),
            ValueType::oneOf(ValueType::bool(), ValueType::map()),
            ValueType::string()->nullable(),
            ValueType::literal('a'),
            ValueType::dateTime(),
        ];

        foreach ($types as $type) {
            try {
                $type->parse(1.5, 'key', 'docs/guide.md');
                $this->fail('The value should be rejected.');
            } catch (TranslatableInvalidArgumentException $e) {
                $this->assertStringStartsWith('The value at "key" of docs/guide.md must be of type ', $e->getMessage());
            }
        }
    }

    #[Test]
    public function shouldSayWhereTheValueComesFromInTheItemThatFails(): void
    {
        $this->expectExceptionMessage('The value at "tags.1" of docs/guide.md must be of type string, int given.');

        ValueType::listOf(ValueType::string())->parse(['a', 1], 'tags', 'docs/guide.md');
    }

    #[Test]
    public function shouldAcceptACustomTypeThatDoesNotKnowTheSource(): void
    {
        $even = new ValueType('even', static fn (mixed $value, string $path): int
            => is_int($value) && $value % 2 === 0 ? $value : throw ValueType::mismatch($path, 'even', $value));

        $this->assertSame(4, $even->parse(4, 'key', 'docs/guide.md'));

        $this->expectExceptionMessage('The value at "key" must be of type even, int given.');

        $even->parse(3, 'key', 'docs/guide.md');
    }

    #[Test]
    public function shouldNameTheTypes(): void
    {
        $this->assertSame('non-empty-string', ValueType::nonEmptyString()->name);
        $this->assertSame("'asc'|'desc'", ValueType::literal('asc', 'desc')->name);
        $this->assertSame('false', ValueType::literal(false)->name);
        $this->assertSame('stdClass', ValueType::instanceOf(stdClass::class)->name);
        $this->assertSame('date', ValueType::dateTime()->name);
        $this->assertSame('list<map<int>>', ValueType::listOf(ValueType::mapOf(ValueType::int()))->name);
        $this->assertSame('bool|map', ValueType::oneOf(ValueType::bool(), ValueType::map())->name);
        $this->assertSame('string|null', ValueType::string()->nullable()->name);
    }

    #[Test]
    public function shouldReportThePathOfTheItemThatFails(): void
    {
        $type = ValueType::mapOf(ValueType::listOf(ValueType::string()));

        try {
            $type->parse(['a' => ['x', 2]], 'dependentRequired');
            $this->fail('The value should be rejected.');
        } catch (TranslatableInvalidArgumentException $e) {
            $this->assertSame(
                'The value at "dependentRequired.a.1" must be of type string, int given.',
                $e->getMessage()
            );
        }
    }

    #[Test]
    public function shouldReportTheTypeOfTheGivenValue(): void
    {
        $error = ValueType::mismatch('k', 'string', new stdClass());

        $this->assertInstanceOf(TranslatableInterface::class, $error);
        $this->assertSame('The value at "k" must be of type string, stdClass given.', $error->getMessage());
    }

    #[Test]
    public function shouldJoinPaths(): void
    {
        $this->assertSame('a', ValueType::path('', 'a'));
        $this->assertSame('0', ValueType::path('', 0));
        $this->assertSame('a.b', ValueType::path('a', 'b'));
        $this->assertSame('a.0', ValueType::path('a', 0));
    }

    #[Test]
    public function shouldAcceptACustomType(): void
    {
        $even = new ValueType('even', static fn (mixed $v, string $p): int
            => is_int($v) && $v % 2 === 0 ? $v : throw ValueType::mismatch($p, 'even', $v));

        $this->assertSame(4, $even->parse(4));
        $this->assertSame([2, 4], ValueType::listOf($even)->parse([2, 4]));

        $this->expectException(TranslatableInvalidArgumentException::class);
        $this->expectExceptionMessage('The value at "0" must be of type even, int given.');
        ValueType::listOf($even)->parse([3]);
    }
}
