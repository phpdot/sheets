<?php

declare(strict_types=1);

/**
 * What a chart's data labels show. Defaults to the value only; pies typically
 * want `percent: true`. Pass to {@see ChartNode}'s `dataLabels`.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Sheets\Engine\Feature\Chart;

final class DataLabels
{
    /**
     * Selects which data-label fields to show (value, category, series name, percent) and their position.
     *
     * @param bool $value
     * @param bool $category
     * @param bool $seriesName
     * @param bool $percent
     * @param ?DataLabelPosition $position
     */
    public function __construct(
        public readonly bool $value = true,
        public readonly bool $category = false,
        public readonly bool $seriesName = false,
        public readonly bool $percent = false,
        public readonly ?DataLabelPosition $position = null,
    ) {}
}
