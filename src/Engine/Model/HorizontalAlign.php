<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Engine\Model;

/**
 * Horizontal cell text alignment. Values are the OOXML tokens.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
enum HorizontalAlign: string
{
    case Left = 'left';
    case Center = 'center';
    case Right = 'right';
    case Fill = 'fill';
    case Justify = 'justify';
}
