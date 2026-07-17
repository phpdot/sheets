<?php

declare(strict_types=1);

/**
 * The kind of data validation. Values are the OOXML tokens.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Sheets\Engine\Feature\Validation;

enum ValidationType: string
{
    case List = 'list';
    case WholeNumber = 'whole';
    case Decimal = 'decimal';
    case Date = 'date';
    case Time = 'time';
    case TextLength = 'textLength';
    case Custom = 'custom';
}
