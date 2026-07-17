<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Tests\Engine\Feature\Chart;

use PHPdot\Sheets\Engine\Feature\Chart\ChartNode;
use PHPdot\Sheets\Engine\Feature\Chart\ChartPlugin;
use PHPdot\Sheets\Engine\Feature\Chart\ChartSeries;
use PHPdot\Sheets\Engine\Feature\Chart\ChartType;
use PHPdot\Sheets\Engine\Model\Color;
use PHPdot\Sheets\Engine\Support\InvalidArgumentException;
use PHPdot\Sheets\Spreadsheet;
use PHPUnit\Framework\TestCase;

final class ChartAdvancedTest extends TestCase
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

    public function testPerSeriesColorOnBarUsesSolidFill(): void
    {
        $node = new ChartNode(
            ChartType::Bar,
            [new ChartSeries('S!$B$2:$B$5', 'X', color: Color::hex('#FF0000'))],
            4,
            0,
            categories: 'S!$A$2:$A$5',
        );
        self::assertStringContainsString(
            '<c:spPr><a:solidFill><a:srgbClr val="FF0000"/></a:solidFill></c:spPr>',
            $this->chartXml($node),
        );
    }

    public function testPerSeriesColorOnLineUsesLineFill(): void
    {
        $node = new ChartNode(
            ChartType::Line,
            [new ChartSeries('S!$B$2:$B$5', 'X', color: Color::hex('#00FF00'))],
            4,
            0,
            categories: 'S!$A$2:$A$5',
        );
        self::assertStringContainsString(
            '<c:spPr><a:ln><a:solidFill><a:srgbClr val="00FF00"/></a:solidFill></a:ln></c:spPr>',
            $this->chartXml($node),
        );
    }

    public function testScatterUsesXyValuesAndTwoValueAxes(): void
    {
        $node = new ChartNode(
            ChartType::Scatter,
            [new ChartSeries('S!$B$2:$B$5', 'Points')],
            4,
            0,
            categories: 'S!$A$2:$A$5',
        );
        $xml = $this->chartXml($node);
        self::assertStringContainsString('<c:scatterChart>', $xml);
        self::assertStringContainsString('<c:xVal><c:numRef><c:f>S!$A$2:$A$5</c:f></c:numRef></c:xVal>', $xml);
        self::assertStringContainsString('<c:yVal><c:numRef><c:f>S!$B$2:$B$5</c:f></c:numRef></c:yVal>', $xml);
        self::assertSame(2, substr_count($xml, '<c:valAx>')); // both axes are value axes
        self::assertStringNotContainsString('<c:catAx>', $xml);
    }

    public function testComboBarPlusLineSharesAxes(): void
    {
        $node = new ChartNode(
            ChartType::Bar,
            [
                new ChartSeries('S!$B$2:$B$5', 'Revenue'),
                new ChartSeries('S!$C$2:$C$5', 'Margin', type: ChartType::Line),
            ],
            4,
            0,
            categories: 'S!$A$2:$A$5',
        );
        $xml = $this->chartXml($node);
        self::assertStringContainsString('<c:barChart>', $xml);
        self::assertStringContainsString('<c:lineChart>', $xml);
        // One shared axis pair for the whole combo.
        self::assertSame(1, substr_count($xml, '<c:catAx>'));
        self::assertSame(1, substr_count($xml, '<c:valAx>'));
        self::assertInstanceOf(\SimpleXMLElement::class, simplexml_load_string($xml));
    }

    public function testLiteralSeriesNameUsesLiteralText(): void
    {
        // CT_SerTx is a choice: refs go in strRef, literals in c:v — a literal
        // pushed through strRef gets evaluated as a (broken) name reference.
        $node = new ChartNode(
            ChartType::Bar,
            [
                new ChartSeries('S!$B$2:$B$5', 'Revenue'),
                new ChartSeries('S!$C$2:$C$5', '\'My Sheet\'!$C$1'),
            ],
            4,
            0,
        );
        $xml = $this->chartXml($node);
        self::assertStringContainsString('<c:tx><c:v>Revenue</c:v></c:tx>', $xml);
        self::assertStringContainsString(
            '<c:tx><c:strRef><c:f>\'My Sheet\'!$C$1</c:f></c:strRef></c:tx>',
            $xml,
        );
    }

    public function testComboSeriesTypeThatCannotShareAxesIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot share');
        new ChartNode(
            ChartType::Bar,
            [new ChartSeries('S!$B$2:$B$5', type: ChartType::Pie)],
            4,
            0,
        );
    }

    public function testComboOverrideOnAPieBaseIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot host');
        new ChartNode(
            ChartType::Pie,
            [new ChartSeries('S!$B$2:$B$5', type: ChartType::Line)],
            4,
            0,
        );
    }

    private function chartXml(ChartNode $node): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'sheets_charta_');
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
