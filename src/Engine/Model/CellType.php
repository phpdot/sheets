<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Engine\Model;

/**
 * The logical type of a cell's value, determining how it is serialized.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
enum CellType: string
{
    case String  = 'string';
    case Number  = 'number';
    case Date    = 'date';     // a numeric serial formatted as a date (see ExcelDate)
    case Bool    = 'bool';
    case Formula = 'formula';
    case Inline  = 'inline';   // an explicitly inline (non-shared) string
    case Error   = 'error';    // an Excel error value (e.g. "#DIV/0!"); the value is the error code
}
