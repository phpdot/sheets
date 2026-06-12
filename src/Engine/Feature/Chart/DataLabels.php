<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Engine\Feature\Chart;

/**
 * What a chart's data labels show. Defaults to the value only; pies typically
 * want `percent: true`. Pass to {@see ChartNode}'s `dataLabels`.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class DataLabels
{
    public function __construct(
        public readonly bool $value = true,
        public readonly bool $category = false,
        public readonly bool $seriesName = false,
        public readonly bool $percent = false,
        public readonly ?DataLabelPosition $position = null,
    ) {}
}
