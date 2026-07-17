<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Tests\Engine\Feature\Chart;

use PHPdot\Sheets\Engine\Feature\Chart\ChartNode;
use PHPdot\Sheets\Engine\Feature\Chart\ChartPlugin;
use PHPdot\Sheets\Engine\Feature\Chart\ChartSeries;
use PHPdot\Sheets\Engine\Feature\Chart\ChartType;
use PHPdot\Sheets\Engine\Feature\Image\ImageNode;
use PHPdot\Sheets\Engine\Feature\Image\ImagePlugin;
use PHPdot\Sheets\Spreadsheet;
use PHPUnit\Framework\TestCase;

final class ChartTest extends TestCase
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

    public function testBarChartWiresChartPartDrawingAndWorksheet(): void
    {
        $path = $this->newPath();
        $writer = Spreadsheet::writer($path)->use(new ChartPlugin());
        $writer->startSheet('Data');
        $writer->addRow(['Q', 'Revenue']);
        $writer->addRow(['Q1', 10]);
        $writer->addRow(['Q2', 20]);
        $writer->add(new ChartNode(
            ChartType::Bar,
            [new ChartSeries('Data!$B$2:$B$3', 'Data!$B$1')],
            column: 3,
            row: 0,
            categories: 'Data!$A$2:$A$3',
            title: 'Revenue & Growth',
        ));
        $writer->close();

        // Chart part: well-formed, right type, refs and (escaped) title present.
        $chart = $this->member($path, 'xl/charts/chart1.xml');
        self::assertInstanceOf(\SimpleXMLElement::class, simplexml_load_string($chart));
        self::assertStringContainsString('<c:barChart>', $chart);
        self::assertStringContainsString('<c:f>Data!$B$2:$B$3</c:f>', $chart);
        self::assertStringContainsString('Revenue &amp; Growth', $chart);

        // Drawing: graphicFrame referencing the chart, wired through drawing rels.
        $drawing = $this->member($path, 'xl/drawings/drawing1.xml');
        self::assertStringContainsString('<xdr:graphicFrame', $drawing);
        if (preg_match('/<c:chart [^>]*r:id="(rId\d+)"/', $drawing, $m) !== 1) {
            self::fail('graphicFrame has no chart relationship.');
        }
        $drawingRels = $this->member($path, 'xl/drawings/_rels/drawing1.xml.rels');
        self::assertStringContainsString('Id="' . $m[1] . '"', $drawingRels);
        self::assertStringContainsString('Target="../charts/chart1.xml"', $drawingRels);

        // Worksheet links the drawing; content types declare the chart part.
        self::assertStringContainsString('<drawing r:id=', $this->member($path, 'xl/worksheets/sheet1.xml'));
        self::assertStringContainsString('/xl/charts/chart1.xml', $this->member($path, '[Content_Types].xml'));
    }

    public function testChartTypesProduceCorrectPlotElement(): void
    {
        self::assertStringContainsString('<c:barChart>', $this->chartXmlFor(ChartType::Bar));
        self::assertStringContainsString('<c:lineChart>', $this->chartXmlFor(ChartType::Line));
        self::assertStringContainsString('<c:pieChart>', $this->chartXmlFor(ChartType::Pie));
    }

    public function testImageAndChartShareASingleDrawing(): void
    {
        $path = $this->newPath();
        $writer = Spreadsheet::writer($path)->use(new ImagePlugin(), new ChartPlugin());
        $writer->startSheet('Data');
        $writer->addRow(['x', 1]);
        $writer->add(new ImageNode("\x89PNGfake", 'png', 0, 3, 32, 32));
        $writer->add(new ChartNode(ChartType::Pie, [new ChartSeries('Data!$B$1:$B$1')], column: 4, row: 3));
        $writer->close();

        // One drawing part with both a picture and a graphic frame.
        $drawing = $this->member($path, 'xl/drawings/drawing1.xml');
        self::assertSame(1, substr_count($drawing, '<xdr:pic>'));
        self::assertSame(1, substr_count($drawing, '<xdr:graphicFrame'));
        self::assertFalse($this->hasMember($path, 'xl/drawings/drawing2.xml'));

        // Its rels carry both an image and a chart relationship.
        $drawingRels = $this->member($path, 'xl/drawings/_rels/drawing1.xml.rels');
        self::assertSame(1, substr_count($drawingRels, '/relationships/image'));
        self::assertSame(1, substr_count($drawingRels, '/relationships/chart'));
    }

    public function testChartNodeIsSkippedWhenPluginNotEnabled(): void
    {
        $path = $this->newPath();
        $writer = Spreadsheet::writer($path); // no ->use(new ChartPlugin())
        $writer->startSheet('Data');
        $writer->addRow(['x', 1]);
        $writer->add(new ChartNode(ChartType::Bar, [new ChartSeries('Data!$B$1:$B$1')], column: 3, row: 0));
        $writer->close();

        self::assertFalse($this->hasMember($path, 'xl/charts/chart1.xml'));
        self::assertFalse($this->hasMember($path, 'xl/drawings/drawing1.xml'));
        self::assertStringNotContainsString('<drawing', $this->member($path, 'xl/worksheets/sheet1.xml'));
    }

    private function chartXmlFor(ChartType $type): string
    {
        $path = $this->newPath();
        $writer = Spreadsheet::writer($path)->use(new ChartPlugin());
        $writer->startSheet('Data');
        $writer->add(new ChartNode($type, [new ChartSeries('Data!$B$1:$B$2')], column: 2, row: 0));
        $writer->close();

        return $this->member($path, 'xl/charts/chart1.xml');
    }

    private function newPath(): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'sheets_chart_');
        $this->tempFiles[] = $path;

        return $path;
    }

    private function member(string $archive, string $name): string
    {
        $zip = new \ZipArchive();
        if ($zip->open($archive) !== true) {
            self::fail(sprintf('Cannot open archive: %s', $archive));
        }
        $data = $zip->getFromName($name);
        $zip->close();

        if ($data === false) {
            self::fail(sprintf('Archive member not found: %s', $name));
        }

        return $data;
    }

    private function hasMember(string $archive, string $name): bool
    {
        $zip = new \ZipArchive();
        if ($zip->open($archive) !== true) {
            return false;
        }
        $found = $zip->locateName($name) !== false;
        $zip->close();

        return $found;
    }
}
