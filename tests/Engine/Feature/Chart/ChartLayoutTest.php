<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Tests\Engine\Feature\Chart;

use PHPdot\Sheets\Engine\Feature\Chart\ChartNode;
use PHPdot\Sheets\Engine\Feature\Chart\ChartPlugin;
use PHPdot\Sheets\Engine\Feature\Chart\ChartSeries;
use PHPdot\Sheets\Engine\Feature\Chart\ChartType;
use PHPdot\Sheets\Engine\Feature\Chart\DataLabelPosition;
use PHPdot\Sheets\Engine\Feature\Chart\DataLabels;
use PHPdot\Sheets\Spreadsheet;
use PHPUnit\Framework\TestCase;

final class ChartLayoutTest extends TestCase
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

    public function testSecondaryAxisCombo(): void
    {
        $node = new ChartNode(
            ChartType::Bar,
            [
                new ChartSeries('S!$B$2:$B$5', 'Revenue'),
                new ChartSeries('S!$C$2:$C$5', 'Margin', type: ChartType::Line, secondaryAxis: true),
            ],
            4,
            0,
            categories: 'S!$A$2:$A$5',
        );
        $xml = $this->chartXml($node);

        // The line block targets the secondary axis pair (cat 444…, val 333…).
        self::assertStringContainsString('<c:lineChart>', $xml);
        self::assertStringContainsString('<c:axId val="444444444"/><c:axId val="333333333"/>', $xml);
        // The secondary value axis is on the right, crossing at max.
        self::assertStringContainsString('<c:valAx><c:axId val="333333333"/>', $xml);
        self::assertStringContainsString('<c:axPos val="r"/>', $xml);
        self::assertStringContainsString('<c:crosses val="max"/>', $xml);
        // The secondary category axis is hidden (delete=1).
        self::assertStringContainsString('<c:catAx><c:axId val="444444444"/>', $xml);
        self::assertMatchesRegularExpression('/444444444"\/>.*?<c:delete val="1"\/>/', $xml);
        self::assertInstanceOf(\SimpleXMLElement::class, simplexml_load_string($xml));
    }

    public function testHorizontalBarSwapsAxes(): void
    {
        $node = new ChartNode(ChartType::BarHorizontal, [new ChartSeries('S!$B$2:$B$5', 'X')], 4, 0, categories: 'S!$A$2:$A$5');
        $xml = $this->chartXml($node);

        self::assertStringContainsString('<c:barDir val="bar"/>', $xml);
        // Category axis on the left, value axis on the bottom.
        self::assertStringContainsString('<c:catAx><c:axId val="111111111"/><c:scaling><c:orientation val="minMax"/></c:scaling><c:delete val="0"/><c:axPos val="l"/>', $xml);
        self::assertStringContainsString('<c:valAx><c:axId val="222222222"/><c:scaling><c:orientation val="minMax"/></c:scaling><c:delete val="0"/><c:axPos val="b"/>', $xml);
    }

    public function testPercentStacked(): void
    {
        $node = new ChartNode(ChartType::Bar, [new ChartSeries('S!$B$2:$B$5', 'X')], 4, 0, categories: 'S!$A$2:$A$5', percentStacked: true);
        $xml = $this->chartXml($node);

        self::assertStringContainsString('<c:grouping val="percentStacked"/>', $xml);
        self::assertStringContainsString('<c:overlap val="100"/>', $xml);
    }

    public function testPiePercentageLabels(): void
    {
        $node = new ChartNode(
            ChartType::Pie,
            [new ChartSeries('S!$B$2:$B$5')],
            4,
            0,
            categories: 'S!$A$2:$A$5',
            dataLabels: new DataLabels(value: false, percent: true),
        );
        $xml = $this->chartXml($node);

        self::assertStringContainsString('<c:showVal val="0"/>', $xml);
        self::assertStringContainsString('<c:showPercent val="1"/>', $xml);
    }

    public function testDataLabelPosition(): void
    {
        $node = new ChartNode(
            ChartType::Bar,
            [new ChartSeries('S!$B$2:$B$5', 'X')],
            4,
            0,
            categories: 'S!$A$2:$A$5',
            dataLabels: new DataLabels(position: DataLabelPosition::OutsideEnd),
        );
        self::assertStringContainsString('<c:dLbls><c:dLblPos val="outEnd"/>', $this->chartXml($node));
    }

    private function chartXml(ChartNode $node): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'sheets_chartl_');
        $this->tempFiles[] = $path;

        $writer = Spreadsheet::writer($path)->use(new ChartPlugin());
        $writer->startSheet('S');
        $writer->addRow(['cat', 'a', 'b']);
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
