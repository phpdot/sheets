<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Tests\Engine\Xlsx;

use PHPdot\Sheets\Engine\Model\SheetOptions;
use PHPdot\Sheets\Spreadsheet;
use PHPUnit\Framework\TestCase;

final class ColumnLayoutTest extends TestCase
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

    public function testManualColumnWidthsAreEmittedBeforeSheetData(): void
    {
        $path = $this->newPath();
        $writer = Spreadsheet::writer($path);
        $writer->startSheet('Data', new SheetOptions(columnWidths: [0 => 30, 1 => 12.5]));
        $writer->addRow(['Name', 'Qty']);
        $writer->close();

        $sheet = $this->sheetXml($path);
        self::assertStringContainsString('<col min="1" max="1" width="30" customWidth="1"/>', $sheet);
        self::assertStringContainsString('<col min="2" max="2" width="12.5" customWidth="1"/>', $sheet);
        self::assertLessThan(strpos($sheet, '<sheetData>'), strpos($sheet, '<cols>'));
        self::assertInstanceOf(\SimpleXMLElement::class, simplexml_load_string($sheet));
    }

    public function testAutoSizeMakesLongerContentWiderColumns(): void
    {
        $path = $this->newPath();
        $writer = Spreadsheet::writer($path);
        $writer->startSheet('Data', new SheetOptions(autoSizeColumns: true));
        $writer->addRow(['A rather long header value', 'X']);
        $writer->addRow(['short', 'YY']);
        $writer->close();

        $sheet = $this->sheetXml($path);
        self::assertStringContainsString('bestFit="1"', $sheet);
        self::assertLessThan(strpos($sheet, '<sheetData>'), strpos($sheet, '<cols>'));

        if (preg_match('/<col min="1"[^>]*width="([\d.]+)"/', $sheet, $a) !== 1) {
            self::fail('No width for column 1.');
        }
        if (preg_match('/<col min="2"[^>]*width="([\d.]+)"/', $sheet, $b) !== 1) {
            self::fail('No width for column 2.');
        }
        self::assertGreaterThan((float) $b[1], (float) $a[1]);
    }

    public function testAutoSizeDeferralPreservesRowDataExactly(): void
    {
        $input = [['Name', 'Score'], ['Alice', 9.5], ['Bob', 42], ['Carol', 7.25]];

        $path = $this->newPath();
        $writer = Spreadsheet::writer($path);
        $writer->startSheet('Data', new SheetOptions(autoSizeColumns: true));
        foreach ($input as $row) {
            $writer->addRow($row);
        }
        $writer->close();

        $reader = Spreadsheet::reader($path);
        $out = [];
        foreach ($reader->values() as $row) {
            $out[] = $row;
        }
        $reader->close();

        self::assertSame($input, $out);
    }

    public function testManualWidthOverridesAutoSizeEstimate(): void
    {
        $path = $this->newPath();
        $writer = Spreadsheet::writer($path);
        $writer->startSheet('Data', new SheetOptions(autoSizeColumns: true, columnWidths: [0 => 99]));
        $writer->addRow(['x', 'a longer value over here']);
        $writer->close();

        // Column 1 is forced to 99 despite its short content; column 2 is auto-sized.
        self::assertStringContainsString('<col min="1" max="1" width="99"', $this->sheetXml($path));
    }

    public function testFreezePanesEmittedBeforeSheetData(): void
    {
        $path = $this->newPath();
        $writer = Spreadsheet::writer($path);
        $writer->startSheet('Data', new SheetOptions(frozenRows: 1, frozenColumns: 2));
        $writer->addRow(['a', 'b', 'c']);
        $writer->close();

        $sheet = $this->sheetXml($path);
        self::assertStringContainsString(
            '<pane xSplit="2" ySplit="1" topLeftCell="C2" activePane="bottomRight" state="frozen"/>',
            $sheet,
        );
        self::assertLessThan(strpos($sheet, '<sheetData>'), strpos($sheet, '<sheetViews>'));
        self::assertInstanceOf(\SimpleXMLElement::class, simplexml_load_string($sheet));
    }

    public function testGridLinesCanBeTurnedOff(): void
    {
        $path = $this->newPath();
        $writer = Spreadsheet::writer($path);
        $writer->startSheet('Data', new SheetOptions(showGridLines: false));
        $writer->addRow(['a']);
        $writer->close();

        self::assertStringContainsString('showGridLines="0"', $this->sheetXml($path));
    }

    public function testAutoSizeStaysMemoryBounded(): void
    {
        $path = $this->newPath();
        $writer = Spreadsheet::writer($path);
        $writer->startSheet('Big', new SheetOptions(autoSizeColumns: true));

        $before = memory_get_usage();
        for ($i = 1; $i <= 50000; $i++) {
            $writer->addRow(['User ' . $i, $i, str_repeat('x', ($i % 20) + 1)]);
        }
        $writer->close();

        // Deferral keeps rows on disk; only one int per column is held in memory.
        self::assertLessThan(4 * 1024 * 1024, memory_get_usage() - $before);
    }

    public function testActivePaneMatchesTheFrozenAxes(): void
    {
        // A row-only split has no right panes, a column-only split no bottom
        // panes — the active pane must be one that exists.
        $cases = [
            ['options' => new SheetOptions(frozenRows: 1), 'pane' => 'bottomLeft'],
            ['options' => new SheetOptions(frozenColumns: 2), 'pane' => 'topRight'],
            ['options' => new SheetOptions(frozenRows: 1, frozenColumns: 2), 'pane' => 'bottomRight'],
        ];

        foreach ($cases as $case) {
            $path = $this->newPath();
            $writer = Spreadsheet::writer($path);
            $writer->startSheet('Data', $case['options']);
            $writer->addRow(['x']);
            $writer->close();

            self::assertStringContainsString('activePane="' . $case['pane'] . '"', $this->sheetXml($path));
        }
    }

    public function testAutoSizeUsesDisplayWidthForMultibyteText(): void
    {
        $path = $this->newPath();
        $writer = Spreadsheet::writer($path);
        $writer->startSheet('Data', new SheetOptions(autoSizeColumns: true));
        $writer->addRow(['日本語']); // 9 UTF-8 bytes, display width 6
        $writer->close();

        // Display width 6 + 2 padding — byte-counting would give 11.
        self::assertStringContainsString('<col min="1" max="1" width="8"', $this->sheetXml($path));
    }

    private function newPath(): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'sheets_cols_');
        $this->tempFiles[] = $path;

        return $path;
    }

    private function sheetXml(string $archive): string
    {
        $zip = new \ZipArchive();
        if ($zip->open($archive) !== true) {
            self::fail(sprintf('Cannot open archive: %s', $archive));
        }
        $data = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if ($data === false) {
            self::fail('Worksheet part not found.');
        }

        return $data;
    }
}
