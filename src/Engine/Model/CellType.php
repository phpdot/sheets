<?php

declare(strict_types=1);

/**
 * The logical type of a cell's value, determining how it is serialized.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Sheets\Engine\Model;

enum CellType: string
{
    case String  = 'string';
    case Number  = 'number';
    case Date    = 'date';
    case Bool    = 'bool';
    case Formula = 'formula';
    case Inline  = 'inline';
    case Error   = 'error';
}
