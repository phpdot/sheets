<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Tests\Engine\Xlsx;

use PHPdot\Sheets\Engine\Model\BorderStyle;
use PHPdot\Sheets\Engine\Model\Cell;
use PHPdot\Sheets\Engine\Model\CellType;
use PHPdot\Sheets\Engine\Model\Color;
use PHPdot\Sheets\Engine\Model\HorizontalAlign;
use PHPdot\Sheets\Engine\Model\NumberFormats;
use PHPdot\Sheets\Engine\Model\Row;
use PHPdot\Sheets\Engine\Model\Style;
use PHPdot\Sheets\Engine\Model\VerticalAlign;
use PHPdot\Sheets\Engine\Support\WriteException;
use PHPdot\Sheets\Spreadsheet;
use PHPUnit\Framework\TestCase;

final class Tier1Test extends TestCase
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

    public function testBordersRenderToStylesAndCellXf(): void
    {
        $styles = $this->stylesFor(
            (new Style())->withBorder(BorderStyle::Thin, Color::hex('#FF0000')),
        );

        self::assertStringContainsString('<borders count="2">', $styles);
        self::assertStringContainsString('<left style="thin"><color rgb="FFFF0000"/></left>', $styles);
        self::assertStringContainsString('<bottom style="thin"><color rgb="FFFF0000"/></bottom>', $styles);
        self::assertStringContainsString('<diagonal/></border>', $styles);
        self::assertMatchesRegularExpression('/<xf[^>]*borderId="1"[^>]*applyBorder="1"/', $styles);
    }

    public function testFontSizeAndFamily(): void
    {
        $styles = $this->stylesFor(
            (new Style())->withFontSize(14)->withFontName('Arial'),
        );

        self::assertStringContainsString('<sz val="14"/>', $styles);
        self::assertStringContainsString('<name val="Arial"/>', $styles);
    }

    public function testAlignmentAndWrap(): void
    {
        $styles = $this->stylesFor(
            (new Style())
                ->withHorizontalAlign(HorizontalAlign::Center)
                ->withVerticalAlign(VerticalAlign::Center)
                ->withWrapText(),
        );

        self::assertStringContainsString(
            '<alignment horizontal="center" vertical="center" wrapText="1"/>',
            $styles,
        );
        self::assertMatchesRegularExpression('/<xf[^>]*applyAlignment="1"/', $styles);
    }

    public function testNumberFormatPreset(): void
    {
        $styles = $this->stylesFor((new Style())->withNumberFormat(NumberFormats::CURRENCY_USD));

        self::assertStringContainsString('formatCode="&quot;$&quot;#,##0.00"', $styles);
    }

    public function testMergedCellsTrailerAfterSheetData(): void
    {
        $path = $this->newPath();
        $writer = Spreadsheet::writer($path);
        $writer->startSheet('Data');
        $writer->addRow(['Quarterly Report']);
        $writer->mergeCells('A1:D1');
        $writer->close();

        $sheet = $this->member($path, 'xl/worksheets/sheet1.xml');
        self::assertStringContainsString('<mergeCells count="1"><mergeCell ref="A1:D1"/></mergeCells>', $sheet);
        self::assertGreaterThan(strpos($sheet, '</sheetData>'), strpos($sheet, '<mergeCells'));
        self::assertInstanceOf(\SimpleXMLElement::class, simplexml_load_string($sheet));
    }

    public function testInvalidMergeRangeThrows(): void
    {
        $path = $this->newPath();
        $writer = Spreadsheet::writer($path);
        $writer->startSheet('Data');

        $this->expectException(WriteException::class);
        $writer->mergeCells('not-a-range');
    }

    public function testRowHeights(): void
    {
        $path = $this->newPath();
        $writer = Spreadsheet::writer($path);
        $writer->startSheet('Data');
        $writer->addRow(['tall'], null, 30.0);
        $writer->addRowObject(new Row([new Cell('also tall', CellType::String)], null, 22.5));
        $writer->close();

        $sheet = $this->member($path, 'xl/worksheets/sheet1.xml');
        self::assertStringContainsString('<row r="1" ht="30" customHeight="1">', $sheet);
        self::assertStringContainsString('<row r="2" ht="22.5" customHeight="1">', $sheet);
    }

    public function testWithFontColorNullClearsTheColor(): void
    {
        $style = (new Style())->withFontColor(Color::hex('#112233'))->withFontColor(null);

        self::assertNull($style->fontColor);
        self::assertTrue($style->isEmpty());
    }

    public function testCombinedStyleProducesValidStylesXml(): void
    {
        $styles = $this->stylesFor(
            (new Style(bold: true))
                ->withFontSize(12)
                ->withFontName('Calibri')
                ->withFontColor(Color::hex('#FFFFFF'))
                ->withBackgroundColor(Color::hex('#2E5496'))
                ->withHorizontalAlign(HorizontalAlign::Center)
                ->withWrapText()
                ->withBorder(BorderStyle::Medium)
                ->withNumberFormat(NumberFormats::PERCENT_2),
        );

        self::assertInstanceOf(\SimpleXMLElement::class, simplexml_load_string($styles));
    }

    private function stylesFor(Style $style): string
    {
        $path = $this->newPath();
        $writer = Spreadsheet::writer($path);
        $id = $writer->registerStyle($style);
        // Dedup sanity: registering the same style again returns the same id.
        self::assertSame($id, $writer->registerStyle($style));
        $writer->startSheet('Data');
        $writer->addRow(['x'], $id);
        $writer->close();

        return $this->member($path, 'xl/styles.xml');
    }

    private function newPath(): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'sheets_tier1_');
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
}
