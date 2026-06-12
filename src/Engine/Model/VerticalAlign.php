<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Engine\Model;

/**
 * Vertical cell text alignment. Values are the OOXML tokens.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
enum VerticalAlign: string
{
    case Top = 'top';
    case Center = 'center';
    case Bottom = 'bottom';
}
