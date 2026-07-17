<?php

declare(strict_types=1);

/**
 * Where a chart legend sits. Values are the OOXML `legendPos` tokens.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Sheets\Engine\Feature\Chart;

enum LegendPosition: string
{
    case Right = 'r';
    case Left = 'l';
    case Top = 't';
    case Bottom = 'b';
}
