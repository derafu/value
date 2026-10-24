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

enum TextStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Numeric = '1';
    case Blank = '';
}

enum NumberStatus: int
{
    case Pending = 0;
    case Approved = 1;
    case Negative = -1;
}

enum OtherTextStatus: string
{
    case Approved = 'approved';
}

enum OtherNumberStatus: int
{
    case Approved = 1;
}

enum PureStatus
{
    case Approved;
}

enum EmptyTextStatus: string
{
}

enum EmptyNumberStatus: int
{
}
