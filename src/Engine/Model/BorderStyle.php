<?php

declare(strict_types=1);

/**
 * A cell border line style. Values are the OOXML tokens.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Sheets\Engine\Model;

enum BorderStyle: string
{
    case Thin = 'thin';
    case Medium = 'medium';
    case Thick = 'thick';
    case Dashed = 'dashed';
    case Dotted = 'dotted';
    case Double = 'double';
}
