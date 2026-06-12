<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Engine\Model;

/**
 * A cell border line style. Values are the OOXML tokens.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
enum BorderStyle: string
{
    case Thin = 'thin';
    case Medium = 'medium';
    case Thick = 'thick';
    case Dashed = 'dashed';
    case Dotted = 'dotted';
    case Double = 'double';
}
