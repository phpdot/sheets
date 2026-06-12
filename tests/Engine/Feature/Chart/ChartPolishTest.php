<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Tests\Engine\Feature\Chart;

use PHPdot\Sheets\Engine\Feature\Chart\ChartNode;
use PHPdot\Sheets\Engine\Feature\Chart\ChartPlugin;
use PHPdot\Sheets\Engine\Feature\Chart\ChartSeries;
use PHPdot\Sheets\Engine\Feature\Chart\ChartType;
use PHPdot\Sheets\Engine\Feature\Chart\DataLabels;
use PHPdot\Sheets\Engine\Feature\Chart\LegendPosition;
use PHPdot\Sheets\Spreadsheet;
use PHPUnit\Framework\TestCase;

final class ChartPolishTest extends TestCase
{
    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        $this->tempFiles = [];
    }

    public function testLegend(): void
    {
        $xml = $this->chartXml($this->bar(legend: LegendPosition::Right));
        self::assertStringContainsString('<c:legend><c:legendPos val="r"/><c:overlay val="0"/></c:legend>', $xml);
    }

    public function testAxisTitles(): void
    {
        $xml = $this->chartXml($this->bar(xAxisTitle: 'Month', yAxisTitle: 'Sales'));
        self::assertStringContainsString('<c:axPos val="b"/><c:title>', $xml);
        self::assertStringContainsString('<a:t>Month</a:t>', $xml);
        self::assertStringContainsString('<c:axPos val="l"/><c:title>', $xml);
        self::assertStringContainsString('<a:t>Sales</a:t>', $xml);
    }

    public function testDataLabels(): void
    {
        $xml = $this->chartXml($this->bar(dataLabels: new DataLabels()));
        self::assertStringContainsString('<c:dLbls><c:showLegendKey val="0"/><c:showVal val="1"/>', $xml);
    }

    public function testStackedBarAddsOverlap(): void
    {
        $xml = $this->chartXml($this->bar(stacked: true));
        self::assertStringContainsString('<c:grouping val="stacked"/>', $xml);
        self::assertStringContainsString('<c:overlap val="100"/>', $xml);
    }

    public function testAreaChart(): void
    {
        $node = new ChartNode(ChartType::Area, [new ChartSeries('S!$B$2:$B$5', 'Sales')], 4, 0, categories: 'S!$A$2:$A$5');
        $xml = $this->chartXml($node);
        self::assertStringContainsString('<c:areaChart>', $xml);
        self::assertStringContainsString('<c:catAx>', $xml);
    }

    public function testDoughnutChart(): void
    {
        $node = new ChartNode(ChartType::Doughnut, [new ChartSeries('S!$B$2:$B$5')], 4, 0);
        $xml = $this->chartXml($node);
        self::assertStringContainsString('<c:doughnutChart>', $xml);
        self::assertStringContainsString('<c:holeSize val="50"/>', $xml);
    }

    public function testFullyPolishedChartIsWellFormed(): void
    {
        $node = new ChartNode(
            ChartType::Bar,
            [new ChartSeries('S!$B$2:$B$5', 'Q1'), new ChartSeries('S!$C$2:$C$5', 'Q2')],
            4,
            0,
            categories: 'S!$A$2:$A$5',
            title: 'Revenue',
            legend: LegendPosition::Bottom,
            xAxisTitle: 'Region',
            yAxisTitle: 'USD',
            dataLabels: new DataLabels(),
            stacked: true,
        );
        self::assertInstanceOf(\SimpleXMLElement::class, simplexml_load_string($this->chartXml($node)));
    }

    private function bar(
        ?LegendPosition $legend = null,
        ?string $xAxisTitle = null,
        ?string $yAxisTitle = null,
        ?DataLabels $dataLabels = null,
        bool $stacked = false,
    ): ChartNode {
        return new ChartNode(
            ChartType::Bar,
            [new ChartSeries('S!$B$2:$B$5', 'Sales')],
            4,
            0,
            categories: 'S!$A$2:$A$5',
            legend: $legend,
            xAxisTitle: $xAxisTitle,
            yAxisTitle: $yAxisTitle,
            dataLabels: $dataLabels,
            stacked: $stacked,
        );
    }

    private function chartXml(ChartNode $node): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'sheets_chartp_');
        $this->tempFiles[] = $path;

        $writer = Spreadsheet::writer($path)->use(new ChartPlugin());
        $writer->startSheet('S');
        $writer->addRow(['Region', 'Sales']);
        $writer->add($node);
        $writer->close();

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            self::fail('Cannot open archive.');
        }
        $data = $zip->getFromName('xl/charts/chart1.xml');
        $zip->close();
        if ($data === false) {
            self::fail('Chart part not found.');
        }

        return $data;
    }
}
