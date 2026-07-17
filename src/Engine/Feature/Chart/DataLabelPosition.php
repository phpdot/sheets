<?php

declare(strict_types=1);

/**
 * Where data labels sit relative to their point. Values are the OOXML `dLblPos`
 * tokens. (Not every position is valid for every chart type — e.g. bars accept
 * center/inside/outside-end, pies accept center/inside-end/outside-end/best-fit.)
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Sheets\Engine\Feature\Chart;

enum DataLabelPosition: string
{
    case Center = 'ctr';
    case InsideEnd = 'inEnd';
    case InsideBase = 'inBase';
    case OutsideEnd = 'outEnd';
    case BestFit = 'bestFit';
}
