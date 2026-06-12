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
use PHPdot\Sheets\Engine\Model\SheetOptions;
use PHPdot\Sheets\Engine\Model\Style;
use PHPdot\Sheets\Spreadsheet;
use PHPUnit\Framework\TestCase;

final class ReaderDepthTest extends TestCase
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

    public function testWrittenStyleIsResolvedOnRead(): void
    {
        $style = (new Style(bold: true))
            ->withFontSize(14)
            ->withFontName('Arial')
            ->withFontColor(Color::hex('#112233'))
            ->withBackgroundColor(Color::hex('#FFFF00'))
            ->withHorizontalAlign(HorizontalAlign::Center)
            ->withWrapText()
            ->withBorder(BorderStyle::Thin, Color::hex('#999999'))
            ->withNumberFormat(NumberFormats::PERCENT_2);

        $path = $this->newPath();
        $writer = Spreadsheet::writer($path);
        $id = $writer->registerStyle($style);
        $writer->startSheet('S');
        $writer->addRow(['x'], $id);
        $writer->close();

        $reader = Spreadsheet::reader($path);
        $cell = $this->firstCell($reader->rows());
        $resolved = $reader->style($cell->styleId);
        $reader->close();

        if ($resolved === null) {
            self::fail('Style was not resolved.');
        }
        self::assertTrue($resolved->bold);
        self::assertSame(14.0, $resolved->fontSize);
        self::assertSame('Arial', $resolved->fontName);
        self::assertSame('112233', $resolved->fontColor?->rgb);
        self::assertSame('FFFF00', $resolved->backgroundColor?->rgb);
        self::assertSame(HorizontalAlign::Center, $resolved->horizontalAlign);
        self::assertTrue($resolved->wrapText);
        self::assertSame('0.00%', $resolved->numberFormat);

        if ($resolved->borders === null || $resolved->borders->top === null) {
            self::fail('Borders were not resolved.');
        }
        self::assertSame(BorderStyle::Thin, $resolved->borders->top->style);
        self::assertSame('999999', $resolved->borders->top->color?->rgb);
    }

    public function testUnstyledCellResolvesToNull(): void
    {
        $path = $this->newPath();
        $writer = Spreadsheet::writer($path);
        $writer->startSheet('S');
        $writer->addRow(['plain']);
        $writer->close();

        $reader = Spreadsheet::reader($path);
        $cell = $this->firstCell($reader->rows());
        self::assertNull($cell->styleId);
        self::assertNull($reader->style($cell->styleId));
        self::assertNull($reader->style(0));
        $reader->close();
    }

    public function testMergedCellsAreReadBack(): void
    {
        $path = $this->newPath();
        $writer = Spreadsheet::writer($path);
        $writer->startSheet('S');
        $writer->addRow(['Title', 'b', 'c']);
        $writer->addRow(['x', 'y', 'z']);
        $writer->mergeCells('A1:C1');
        $writer->mergeCells('A2:A5');
        $writer->close();

        $reader = Spreadsheet::reader($path);
        $merges = $reader->mergedCells();
        $reader->close();

        sort($merges);
        self::assertSame(['A1:C1', 'A2:A5'], $merges);
    }

    public function testColumnWidthsAreReadBack(): void
    {
        $path = $this->newPath();
        $writer = Spreadsheet::writer($path);
        $writer->startSheet('S', new SheetOptions(columnWidths: [0 => 30, 2 => 12]));
        $writer->addRow(['a', 'b', 'c']);
        $writer->close();

        $reader = Spreadsheet::reader($path);
        $widths = $reader->columnWidths();
        $reader->close();

        ksort($widths);
        self::assertSame([1 => 30.0, 3 => 12.0], $widths);
    }

    public function testHyperlinksAreReadBack(): void
    {
        $path = $this->newPath();
        $writer = Spreadsheet::writer($path);
        $writer->startSheet('S');
        $writer->addRow(['link']);
        $writer->hyperlink('A1', 'https://phpdot.com');
        $writer->close();

        $reader = Spreadsheet::reader($path);
        $links = $reader->hyperlinks();
        $reader->close();

        self::assertSame(['A1' => 'https://phpdot.com'], $links);
    }

    public function testCommentsAreReadBack(): void
    {
        $path = $this->newPath();
        $writer = Spreadsheet::writer($path);
        $writer->startSheet('S');
        $writer->addRow(['x']);
        $writer->comment('A1', 'Please review', 'Omar');
        $writer->close();

        $reader = Spreadsheet::reader($path);
        $comments = $reader->comments();
        $reader->close();

        self::assertSame(['A1' => 'Please review'], $comments);
    }

    public function testFormulasAreReadBack(): void
    {
        $path = $this->newPath();
        $writer = Spreadsheet::writer($path);
        $writer->startSheet('S');
        $writer->addRowObject(new Row([new Cell('B1*C1', CellType::Formula)]));
        $writer->close();

        $reader = Spreadsheet::reader($path);
        $formulas = $reader->formulas();
        $reader->close();

        self::assertSame(['A1' => 'B1*C1'], $formulas);
    }

    /**
     * @param iterable<int, list<Cell>> $rows
     */
    private function firstCell(iterable $rows): Cell
    {
        foreach ($rows as $cells) {
            if ($cells !== []) {
                return $cells[0];
            }
        }

        self::fail('No cells found.');
    }

    private function newPath(): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'sheets_rd_');
        $this->tempFiles[] = $path;

        return $path;
    }
}
