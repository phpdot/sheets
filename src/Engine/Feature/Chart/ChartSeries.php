<?php

declare(strict_types=1);

/**
 * One data series of a chart, referencing worksheet ranges by formula string
 * (e.g. values `Sheet1!$B$2:$B$10`, name `Sheet1!$B$1`). Optionally an explicit
 * `color`, a per-series `type` override (for combo charts), and `secondaryAxis`
 * to plot it against a secondary value axis (the right-hand scale of a combo).
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Sheets\Engine\Feature\Chart;

use PHPdot\Sheets\Engine\Model\Color;

final class ChartSeries
{
    /**
     * Wraps one series: its values range, optional name, colour, per-series type and secondary-axis flag.
     *
     * @param string $valuesRef
     * @param ?string $name
     * @param ?Color $color
     * @param ?ChartType $type
     * @param bool $secondaryAxis
     */
    public function __construct(
        public readonly string $valuesRef,
        public readonly ?string $name = null,
        public readonly ?Color $color = null,
        public readonly ?ChartType $type = null,
        public readonly bool $secondaryAxis = false,
    ) {}
}
