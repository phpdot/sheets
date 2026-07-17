<?php

declare(strict_types=1);

/**
 * A chart being built on a sheet — returned by {@see Sheet::addChart()}. Type,
 * series, labels, legend, axis titles, data labels, and stacking are set fluently;
 * `->at()` places it. Committed to the engine when the sheet flushes.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Sheets\Builder;

use PHPdot\Sheets\Engine\Feature\Chart\ChartNode;
use PHPdot\Sheets\Engine\Feature\Chart\ChartSeries;
use PHPdot\Sheets\Engine\Feature\Chart\ChartType;
use PHPdot\Sheets\Engine\Feature\Chart\DataLabelPosition;
use PHPdot\Sheets\Engine\Feature\Chart\DataLabels;
use PHPdot\Sheets\Engine\Feature\Chart\LegendPosition;
use PHPdot\Sheets\Engine\Feature\FeatureNode;
use PHPdot\Sheets\Engine\Model\Color;
use PHPdot\Sheets\Engine\Support\InvalidArgumentException;
use PHPdot\Sheets\Engine\Support\RuntimeException;

final class Chart implements FeatureBuilder
{
    use CellAnchor;

    private readonly ChartType $type;

    /**
     * @var list<ChartSeries>
     */

    private array $series = [];
    private ?string $cell = null;
    private int $widthPx = 480;
    private int $heightPx = 288;
    private ?string $categories = null;
    private ?string $title = null;
    private ?LegendPosition $legend = null;
    private ?string $xAxisTitle = null;
    private ?string $yAxisTitle = null;
    private ?DataLabels $dataLabels = null;
    private bool $stacked = false;
    private bool $percentStacked = false;

    /**
     * Starts a chart of the given type (a ChartType or a type name like "bar").
     *
     * @param ChartType|string $type
     */
    public function __construct(ChartType|string $type)
    {
        $this->type = $type instanceof ChartType ? $type : $this->toChartType($type);
    }

    /**
     * Sets the chart title.
     *
     * @param string $title
     *
     * @return self
     */
    public function title(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Add a data series referencing a worksheet range (e.g. 'Sales!$B$2:$B$10').
     * The name may be a range ref or literal text. `as` overrides the type for a
     * combo (bar/line/area only); `secondaryAxis` plots it on the right-hand scale.
     *
     * @param string $valuesRef
     * @param ?string $name
     * @param Color|string|null $color
     * @param ChartType|string|null $as
     * @param bool $secondaryAxis
     *
     * @return Chart
     */
    public function series(
        string $valuesRef,
        ?string $name = null,
        Color|string|null $color = null,
        ChartType|string|null $as = null,
        bool $secondaryAxis = false,
    ): self {
        $this->series[] = new ChartSeries(
            $valuesRef,
            $name,
            $color === null ? null : $this->toColor($color),
            $as === null ? null : ($as instanceof ChartType ? $as : $this->toChartType($as)),
            $secondaryAxis,
        );

        return $this;
    }

    /**
     * Sets the category-axis labels from a worksheet range reference.
     *
     * @param string $categoriesRef
     *
     * @return self
     */
    public function labels(string $categoriesRef): self
    {
        $this->categories = $categoriesRef;

        return $this;
    }

    /**
     * Legend position: a {@see LegendPosition} or one of right/left/top/bottom.
     *
     * @param LegendPosition|string $position
     *
     * @return Chart
     */
    public function legend(LegendPosition|string $position): self
    {
        $this->legend = $position instanceof LegendPosition ? $position : $this->toLegend($position);

        return $this;
    }

    /**
     * Sets the x-axis title.
     *
     * @param string $title
     *
     * @return self
     */
    public function xTitle(string $title): self
    {
        $this->xAxisTitle = $title;

        return $this;
    }

    /**
     * Sets the y-axis title.
     *
     * @param string $title
     *
     * @return self
     */
    public function yTitle(string $title): self
    {
        $this->yAxisTitle = $title;

        return $this;
    }

    /**
     * Show data labels. Position: a {@see DataLabelPosition} or one of
     * center/insideEnd/insideBase/outsideEnd/bestFit.
     *
     * @param bool $value
     * @param bool $category
     * @param bool $seriesName
     * @param bool $percent
     * @param DataLabelPosition|string|null $position
     *
     * @return Chart
     */
    public function dataLabels(
        bool $value = true,
        bool $category = false,
        bool $seriesName = false,
        bool $percent = false,
        DataLabelPosition|string|null $position = null,
    ): self {
        $this->dataLabels = new DataLabels(
            $value,
            $category,
            $seriesName,
            $percent,
            $position === null
                ? null
                : ($position instanceof DataLabelPosition ? $position : $this->toLabelPosition($position)),
        );

        return $this;
    }

    /**
     * Stacks the series on top of one another.
     *
     * @return self
     */
    public function stacked(): self
    {
        $this->stacked = true;

        return $this;
    }

    /**
     * Stacks the series so each category fills 100 percent.
     *
     * @return self
     */
    public function stacked100(): self
    {
        $this->percentStacked = true;

        return $this;
    }

    /**
     * Place the chart with its top-left at an A1 cell, optionally sized in pixels.
     *
     * @param array{0: int, 1: int}|null $size [width, height] in pixels
     * @param string $cell
     *
     * @return Chart
     */
    public function at(string $cell, ?array $size = null): self
    {
        $this->cell = $cell;
        if ($size !== null) {
            $this->widthPx = $size[0];
            $this->heightPx = $size[1];
        }

        return $this;
    }

    public function toFeatureNode(): FeatureNode
    {
        if ($this->cell === null) {
            throw new RuntimeException('Chart needs a position — call ->at($cell).');
        }
        if ($this->series === []) {
            throw new RuntimeException('Chart needs at least one ->series(...).');
        }

        [$column, $row] = $this->parseCellRef($this->cell);

        return new ChartNode(
            type: $this->type,
            series: $this->series,
            column: $column,
            row: $row,
            widthPx: $this->widthPx,
            heightPx: $this->heightPx,
            categories: $this->categories,
            title: $this->title,
            legend: $this->legend,
            xAxisTitle: $this->xAxisTitle,
            yAxisTitle: $this->yAxisTitle,
            dataLabels: $this->dataLabels,
            stacked: $this->stacked,
            percentStacked: $this->percentStacked,
        );
    }

    /**
     * Maps a chart-type name onto a ChartType, rejecting unknown names.
     *
     * @param string $type
     *
     * @return ChartType
     */
    private function toChartType(string $type): ChartType
    {
        return match (strtolower($type)) {
            'bar', 'column' => ChartType::Bar,
            'barh', 'barhorizontal' => ChartType::BarHorizontal,
            'line' => ChartType::Line,
            'pie' => ChartType::Pie,
            'area' => ChartType::Area,
            'doughnut', 'donut' => ChartType::Doughnut,
            'scatter' => ChartType::Scatter,
            default => throw new InvalidArgumentException(sprintf(
                'Unknown chart type "%s". Use bar, barh, line, pie, area, doughnut, or scatter.',
                $type,
            )),
        };
    }

    /**
     * Maps a legend-position name onto a LegendPosition, rejecting unknown names.
     *
     * @param string $position
     *
     * @return LegendPosition
     */
    private function toLegend(string $position): LegendPosition
    {
        return match (strtolower($position)) {
            'right' => LegendPosition::Right,
            'left' => LegendPosition::Left,
            'top' => LegendPosition::Top,
            'bottom' => LegendPosition::Bottom,
            default => throw new InvalidArgumentException(sprintf(
                'Unknown legend position "%s". Use right, left, top, or bottom.',
                $position,
            )),
        };
    }

    /**
     * Maps a data-label-position name onto a DataLabelPosition, rejecting unknown names.
     *
     * @param string $position
     *
     * @return DataLabelPosition
     */
    private function toLabelPosition(string $position): DataLabelPosition
    {
        return match (strtolower($position)) {
            'center' => DataLabelPosition::Center,
            'insideend' => DataLabelPosition::InsideEnd,
            'insidebase' => DataLabelPosition::InsideBase,
            'outsideend' => DataLabelPosition::OutsideEnd,
            'bestfit' => DataLabelPosition::BestFit,
            default => throw new InvalidArgumentException(sprintf(
                'Unknown data-label position "%s". Use center, insideEnd, insideBase, outsideEnd, or bestFit.',
                $position,
            )),
        };
    }

    /**
     * Normalises a Color or hex string into a Color.
     *
     * @param Color|string $color
     *
     * @return Color
     */
    private function toColor(Color|string $color): Color
    {
        return $color instanceof Color ? $color : Color::hex($color);
    }
}
