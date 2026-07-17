<?php

declare(strict_types=1);

/**
 * A format-neutral chart anchored to a sheet: its type, series (range refs), an
 * optional category-axis range and title, plus a top-left anchor cell (0-based)
 * and pixel size. Optional polish: legend, axis titles, data labels, stacking
 * (incl. 100%), and (via per-series flags) combos with a secondary axis.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Sheets\Engine\Feature\Chart;

use PHPdot\Sheets\Engine\Feature\Capability;
use PHPdot\Sheets\Engine\Feature\FeatureNode;
use PHPdot\Sheets\Engine\Support\InvalidArgumentException;

final class ChartNode implements FeatureNode
{
    /**
     * Chart kinds that can share a category/value axis pair — the only valid
     * participants in a combo (per-series type overrides).
     */
    private const COMBO_TYPES = [ChartType::Bar, ChartType::BarHorizontal, ChartType::Line, ChartType::Area];

    /**
     * Builds an immutable chart node from its type, series, sheet anchor, size and optional polish.
     *
     * @param list<ChartSeries> $series
     * @param bool $stacked Stack series (bar/column/line/area); ignored by pie/doughnut.
     * @param bool $percentStacked 100%-stacked (share of total); implies stacking.
     * @param ChartType $type
     * @param int $column
     * @param int $row
     * @param int $widthPx
     * @param int $heightPx
     * @param ?string $categories
     * @param ?string $title
     * @param ?LegendPosition $legend
     * @param ?string $xAxisTitle
     * @param ?string $yAxisTitle
     * @param ?DataLabels $dataLabels
     *
     * @throws InvalidArgumentException When a per-series type override is used
     *                                  on a base type that cannot host a combo, or overrides to a type
     *                                  (pie/doughnut/scatter) that cannot share the combo's axes.
     */
    public function __construct(
        public readonly ChartType $type,
        public readonly array $series,
        public readonly int $column,
        public readonly int $row,
        public readonly int $widthPx = 480,
        public readonly int $heightPx = 288,
        public readonly ?string $categories = null,
        public readonly ?string $title = null,
        public readonly ?LegendPosition $legend = null,
        public readonly ?string $xAxisTitle = null,
        public readonly ?string $yAxisTitle = null,
        public readonly ?DataLabels $dataLabels = null,
        public readonly bool $stacked = false,
        public readonly bool $percentStacked = false,
    ) {
        foreach ($this->series as $oneSeries) {
            if ($oneSeries->type === null) {
                continue;
            }
            if (!in_array($this->type, self::COMBO_TYPES, true)) {
                throw new InvalidArgumentException(sprintf(
                    'Per-series type overrides (combo charts) require a bar/line/area base chart; "%s" cannot host one.',
                    $this->type->value,
                ));
            }
            if (!in_array($oneSeries->type, self::COMBO_TYPES, true)) {
                throw new InvalidArgumentException(sprintf(
                    'A combo series must be bar, horizontal bar, line or area; "%s" cannot share the combo axes.',
                    $oneSeries->type->value,
                ));
            }
        }
    }

    public function capability(): Capability
    {
        return Capability::Charts;
    }
}
