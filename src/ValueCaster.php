<?php

declare(strict_types=1);

/**
 * Derafu: Value - Typed Value Validation and Conversion.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Value;

use Derafu\Value\Exception\TypeMismatchException;
use Derafu\Value\Exception\ValueConversionException;
use Stringable;
use Throwable;

/**
 * Strict scalar reads and explicit conversions, independent of array keys.
 *
 * getXXX() accepts null; requireXXX() rejects it. Reads preserve existing types,
 * with string reads also accepting Stringable through explicit text conversion.
 * toXXX() preserves null and converts supported input forms. Numeric conversions
 * accept complete PHP numeric strings, including decimal exponents; boolean
 * inputs are never converted to numbers. Invalid input throws without fallback.
 */
final class ValueCaster
{
    /**
     * Reads text without accepting numeric or boolean input. Null is preserved.
     *
     * @throws TypeMismatchException If the input is not text or Stringable conversion fails.
     */
    public static function getString(mixed $value): ?string
    {
        return $value === null ? null : self::requireString($value);
    }

    /**
     * Requires string or Stringable input, without trimming the resulting text.
     *
     * Stringable conversion delegates to toString() through ValueType, keeping
     * the original cause if conversion fails.
     *
     * @throws TypeMismatchException If the input is not text or Stringable conversion fails.
     */
    public static function requireString(mixed $value): string
    {
        $text = ValueType::oneOf(
            ValueType::string(),
            ValueType::instanceOf(Stringable::class)
        )->parse($value);

        return ValueType::toString()->parse($text);
    }

    /**
     * Reads an existing integer without conversion. Null is preserved.
     *
     * @throws TypeMismatchException If the non-null input is not an int.
     */
    public static function getInt(mixed $value): ?int
    {
        return $value === null ? null : self::requireInt($value);
    }

    /**
     * Requires an existing integer without conversion.
     *
     * @throws TypeMismatchException If the input is not an int, including null.
     */
    public static function requireInt(mixed $value): int
    {
        return ValueType::int()->parse($value);
    }

    /**
     * Reads an existing float without conversion. Null is preserved.
     *
     * @throws TypeMismatchException If the non-null input is not a float.
     */
    public static function getFloat(mixed $value): ?float
    {
        return $value === null ? null : self::requireFloat($value);
    }

    /**
     * Requires an existing float without converting integers.
     *
     * @throws TypeMismatchException If the input is not a float, including null.
     */
    public static function requireFloat(mixed $value): float
    {
        return ValueType::float()->parse($value);
    }

    /**
     * Reads an existing integer or float, preserving its type and null.
     *
     * @throws TypeMismatchException If the non-null input is not a number.
     */
    public static function getNumber(mixed $value): int|float|null
    {
        return $value === null ? null : self::requireNumber($value);
    }

    /**
     * Requires an existing integer or float, preserving its type.
     *
     * @throws TypeMismatchException If the input is not a number, including null.
     */
    public static function requireNumber(mixed $value): int|float
    {
        return ValueType::number()->parse($value);
    }

    /**
     * Reads an existing boolean without conversion. Null is preserved.
     *
     * @throws TypeMismatchException If the non-null input is not a bool.
     */
    public static function getBool(mixed $value): ?bool
    {
        return $value === null ? null : self::requireBool($value);
    }

    /**
     * Requires an existing boolean without conversion.
     *
     * @throws TypeMismatchException If the input is not a bool, including null.
     */
    public static function requireBool(mixed $value): bool
    {
        return ValueType::bool()->parse($value);
    }

    /**
     * Converts an exact integer within the platform range, without truncation.
     *
     * Numeric strings are interpreted as decimal values without a float
     * intermediate, so fractions and integers beyond the range cannot be
     * hidden by rounding. Integral native floats are accepted as represented.
     *
     * @throws ValueConversionException If the value cannot represent an int.
     */
    public static function toInt(mixed $value): ?int
    {
        if ($value === null || is_int($value)) {
            return $value;
        }
        if (is_float($value)) {
            // The positive exclusive bound is exactly representable as float;
            // PHP_INT_MAX itself can round up when compared with a float.
            if (!is_finite($value) || floor($value) !== $value
                || $value < PHP_INT_MIN || $value >= -(float) PHP_INT_MIN) {
                throw self::failure($value, 'int');
            }

            return (int) $value;
        }
        if (!is_string($value) || !is_numeric($value)) {
            throw self::failure($value, 'int');
        }

        return self::integerFromString($value);
    }

    /**
     * Converts numbers and numeric strings using PHP float representation.
     *
     * Rounding, infinity and underflow to zero are permitted. Native NAN is
     * preserved; the strings "NAN" and "INF" are not numeric input.
     *
     * @throws ValueConversionException If the input is not a number or numeric string.
     */
    public static function toFloat(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }
        if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) {
            return (float) $value;
        }

        throw self::failure($value, 'float');
    }

    /**
     * Converts to an exact int when possible, otherwise delegates to toFloat().
     *
     * Null is preserved. If both conversions fail, the float conversion's
     * exception is propagated.
     *
     * @throws ValueConversionException If neither conversion accepts the value.
     */
    public static function toNumber(mixed $value): int|float|null
    {
        try {
            return self::toInt($value);
        } catch (ValueConversionException) {
            return self::toFloat($value);
        }
    }

    /**
     * Converts booleans, numeric zero/one and PHP boolean text tokens.
     *
     * Text is trimmed and case insensitive: true/yes/on/1 and
     * false/no/off/0/empty. Other inputs are rejected, not treated as false.
     *
     * @throws ValueConversionException If the input is not a recognized boolean.
     */
    public static function toBool(mixed $value): ?bool
    {
        if ($value === null || is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            if ($value === 0 || $value === 0.0) {
                return false;
            }
            if ($value === 1 || $value === 1.0) {
                return true;
            }
        } elseif (is_string($value)) {
            $result = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
            if ($result !== null) {
                return $result;
            }
        }

        throw self::failure($value, 'bool');
    }

    /**
     * Converts scalars and Stringable objects without trimming text.
     *
     * Booleans become "1" and "0". Numbers use PHP's string representation.
     *
     * @throws ValueConversionException If conversion is unsupported or __toString fails.
     */
    public static function toString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_float($value) && is_nan($value)) {
            // PHP 8.5 warns when casting NAN to string; its text remains NAN.
            return 'NAN';
        }
        if (is_scalar($value) || $value instanceof Stringable) {
            try {
                return (string) $value;
            } catch (Throwable $exception) {
                throw self::failure($value, 'string', $exception);
            }
        }

        throw self::failure($value, 'string');
    }

    /**
     * Interprets a validated numeric string using decimal digits only.
     */
    private static function integerFromString(string $value): int
    {
        $number = strtolower(trim($value, " \t\n\r\v\f"));
        $negative = str_starts_with($number, '-');
        $number = ltrim($number, '+-');
        $parts = explode('e', $number);
        $mantissa = explode('.', $parts[0]);
        $fraction = $mantissa[1] ?? '';
        $digits = ltrim($mantissa[0] . $fraction, '0');
        if ($digits === '') {
            return 0;
        }

        // Bound the exponent before arithmetic or allocating padding. Larger
        // exponents can only overflow or leave a nonzero fractional value.
        $exponentText = $parts[1] ?? '0';
        $magnitude = ltrim(ltrim($exponentText, '+-'), '0');
        $bound = (string) (strlen($digits) + strlen($fraction) + strlen((string) PHP_INT_MIN));
        if (strlen($magnitude) > strlen($bound)
            || (strlen($magnitude) === strlen($bound) && strcmp($magnitude, $bound) > 0)) {
            throw self::failure($value, 'int');
        }
        $shift = (int) $exponentText - strlen($fraction);
        if ($shift < 0) {
            $places = -$shift;
            if ($places > strlen($digits) || substr($digits, -$places) !== str_repeat('0', $places)) {
                throw self::failure($value, 'int');
            }
            $digits = substr($digits, 0, -$places);
        } else {
            if (strlen($digits) + $shift > strlen((string) PHP_INT_MIN)) {
                throw self::failure($value, 'int');
            }
            $digits .= str_repeat('0', $shift);
        }

        $limit = $negative ? substr((string) PHP_INT_MIN, 1) : (string) PHP_INT_MAX;
        if (strlen($digits) > strlen($limit)
            || (strlen($digits) === strlen($limit) && strcmp($digits, $limit) > 0)) {
            throw self::failure($value, 'int');
        }

        return (int) ($negative ? '-' . $digits : $digits);
    }

    /**
     * Builds a conversion error without array-specific context.
     */
    private static function failure(mixed $value, string $target, ?Throwable $previous = null): ValueConversionException
    {
        return new ValueConversionException([
            'A value of type {given} cannot be converted to {target}.',
            'given' => get_debug_type($value),
            'target' => $target,
        ], previous: $previous);
    }
}
