<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Engine\Feature\Validation;

/**
 * Comparison operators for a `cellIs` conditional-formatting rule. Values are the
 * OOXML operator tokens.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
enum CfOperator: string
{
    case GreaterThan = 'greaterThan';
    case LessThan = 'lessThan';
    case GreaterThanOrEqual = 'greaterThanOrEqual';
    case LessThanOrEqual = 'lessThanOrEqual';
    case Equal = 'equal';
    case NotEqual = 'notEqual';
    case Between = 'between';
    case NotBetween = 'notBetween';
}
