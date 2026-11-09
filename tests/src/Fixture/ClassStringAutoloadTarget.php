<?php

declare(strict_types=1);

/**
 * Derafu: Value - Typed Value Validation and Conversion.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsValue\Fixture;

use ArrayObject;
use LogicException;

/**
 * An autoloadable class whose constructor must not run during validation.
 *
 * @extends ArrayObject<array-key, mixed>
 */
final class ClassStringAutoloadTarget extends ArrayObject
{
    public function __construct()
    {
        throw new LogicException('Class-name validation must not instantiate its target.');
    }
}
