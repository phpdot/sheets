<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Engine\Feature\Chart;

/**
 * The supported chart kinds.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
enum ChartType: string
{
    case Bar = 'bar';
    case BarHorizontal = 'barHorizontal';
    case Line = 'line';
    case Pie = 'pie';
    case Area = 'area';
    case Doughnut = 'doughnut';
    case Scatter = 'scatter';
}
