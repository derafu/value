<?php

declare(strict_types=1);

/**
 * Derafu: Value - Typed Value Validation and Conversion.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

return [
    'A value of type {given} cannot be converted to {target}.' =>
        'Un valor de tipo {given} no se puede convertir a {target}.',
    'The class {class} must be a backed enum.' =>
        'La clase {class} debe ser un enum respaldado.',
    'The value at "{path}" must be of type {expected}, {given} given.' =>
        'El valor en "{path}" debe ser de tipo {expected}, se entregó {given}.',
    'The value at "{path}" of {source} must be of type {expected}, {given} given.' =>
        'El valor en "{path}" de {source} debe ser de tipo {expected}, se entregó {given}.',
];
