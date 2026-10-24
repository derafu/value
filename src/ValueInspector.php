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

/**
 * Inspects values without converting or normalizing them.
 */
final class ValueInspector
{
    /**
     * Whether the value is null, an empty string or an empty array.
     *
     * False, zero and whitespace-only strings are not blank. Arrays are not
     * inspected recursively, and objects are not converted or counted.
     */
    public static function isBlank(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }
}
