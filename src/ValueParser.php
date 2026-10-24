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

use Derafu\Value\Exception\ValueConversionException;
use Stringable;

/**
 * Interprets individual values without traversing arrays or normalizing text.
 */
final class ValueParser
{
    /**
     * Interprets literals and delegates scalar conversion to ValueCaster.
     *
     * Tokens are case insensitive and recognized after trimming exterior
     * whitespace. Unrecognized strings keep their original content, including
     * whitespace. Stringable objects are converted to text before parsing;
     * all other values are returned unchanged.
     * Conversion tries toInt(), toFloat() and toBool(), in that order.
     *
     * @param bool $preserveNumericFormat Keep numeric text when converting it
     * back through ValueCaster::toString() changes its original spelling.
     * @param bool $preserveNumericPrecision Keep numeric text when conversion
     * produces a non-finite float or loses significant decimal digits. This
     * compares the shortest decimal representation that reproduces the float;
     * it does not require exact binary representation of decimal fractions.
     * @throws ValueConversionException If a Stringable object's conversion fails.
     */
    public static function parse(
        mixed $value,
        bool $preserveNumericFormat = false,
        bool $preserveNumericPrecision = false
    ): mixed {
        if ($value instanceof Stringable) {
            $value = ValueCaster::toString($value);
        }

        if (!is_string($value)) {
            return $value;
        }

        switch (strtolower(trim($value))) {
            case 'null':
            case '(null)':
                return null;
            case 'empty':
            case '(empty)':
                return '';
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
        }

        try {
            $number = ValueCaster::toNumber($value);
        } catch (ValueConversionException) {
            try {
                return ValueCaster::toBool($value);
            } catch (ValueConversionException) {
                return $value;
            }
        }

        if ($preserveNumericFormat && ValueCaster::toString($number) !== $value) {
            return $value;
        }
        if ($preserveNumericPrecision && is_float($number) && !self::preservesPrecision($value, $number)) {
            return $value;
        }

        return $number;
    }

    /**
     * Compares decimal significant digits after a successful numeric conversion.
     *
     * At most 17 significant decimal digits are needed to reproduce a PHP
     * float. H formats numbers independently of the process locale.
     */
    private static function preservesPrecision(string $value, float $number): bool
    {
        if (!is_finite($number)) {
            return false;
        }

        for ($precision = 1; ; $precision++) {
            $text = sprintf('%.' . $precision . 'H', $number);
            if ((float) $text === $number || $precision === 17) {
                break;
            }
        }

        return self::significantDigits(trim($value, " \t\n\r\v\f")) === self::significantDigits($text);
    }

    /**
     * Extracts significant mantissa digits from already interpreted numbers.
     */
    private static function significantDigits(string $number): string
    {
        $mantissa = explode('e', strtolower($number))[0];

        return trim(str_replace(['+', '-', '.'], '', $mantissa), '0');
    }
}
