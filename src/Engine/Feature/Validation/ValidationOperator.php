<?php

declare(strict_types=1);

/**
 * Comparison operator for a numeric/date/text-length data validation. Values are
 * the OOXML tokens.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Sheets\Engine\Feature\Validation;

enum ValidationOperator: string
{
    case Between = 'between';
    case NotBetween = 'notBetween';
    case Equal = 'equal';
    case NotEqual = 'notEqual';
    case GreaterThan = 'greaterThan';
    case LessThan = 'lessThan';
    case GreaterThanOrEqual = 'greaterThanOrEqual';
    case LessThanOrEqual = 'lessThanOrEqual';
}
