<?php

declare(strict_types=1);

/**
 * Derafu: Value - Typed Value Validation and Conversion.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Value\Exception;

use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException;

/**
 * Invalid value arguments or type definitions.
 */
class ValueException extends TranslatableInvalidArgumentException
{
}
