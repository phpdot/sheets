<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Tests\Engine\Xlsx;

use PHPdot\Sheets\Engine\Model\Cell;
use PHPdot\Sheets\Engine\Model\CellType;
use PHPdot\Sheets\Engine\Model\Row;
use PHPdot\Sheets\Engine\Model\WriteOptions;
use PHPdot\Sheets\Engine\Support\WriteException;
use PHPdot\Sheets\Spreadsheet;
use PHPUnit\Framework\TestCase;

/**
 * Regression suite for the silent-failure class of bugs: user data that used to
 * corrupt output (or vanish) must now either round-trip exactly or throw.
 */
final class HardeningTest extends TestCase
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

    public function testHyperlinkUrlWithAmpersandRoundTrips(): void
    {
        $url = 'https://example.com/?a=1&b=2&q="x"';

        $path = $this->newPath();
        $writer = Spreadsheet::writer($path);
        $writer->startSheet('Data');
        $writer->addRow(['link']);
        $writer->hyperlink('A1', $url);
        $writer->close();

        // The rels part must stay well-formed XML despite the raw & and quotes.
        $rels = $this->member($path, 'xl/worksheets/_rels/sheet1.xml.rels');
        self::assertInstanceOf(\SimpleXMLElement::class, simplexml_load_string($rels));

        $reader = Spreadsheet::reader($path);
        self::assertSame(['A1' => $url], $reader->hyperlinks());
        $reader->close();
    }

    public function testHyperlinkRelationshipsAreDeduplicatedPerUrl(): void
    {
        $path = $this->newPath();
        $writer = Spreadsheet::writer($path);
        $writer->startSheet('Data');
        $writer->addRow(['a', 'b']);
        $writer->hyperlink('A1', 'https://example.com/');
        $writer->hyperlink('B1', 'https://example.com/');
        $writer->close();

        $rels = $this->member($path, 'xl/worksheets/_rels/sheet1.xml.rels');
        self::assertSame(1, substr_count($rels, '<Relationship '));

        $reader = Spreadsheet::reader($path);
        self::assertCount(2, $reader->hyperlinks());
        $reader->close();
    }

    public function testNanThrowsInsteadOfWritingZero(): void
    {
        $writer = Spreadsheet::writer($this->newPath());
        $writer->startSheet('Data');

        $this->expectException(WriteException::class);
        $this->expectExceptionMessage('non-finite');
        $writer->addRow([NAN]);
    }

    public function testInfinityThrowsInsteadOfWritingZero(): void
    {
        $writer = Spreadsheet::writer($this->newPath());
        $writer->startSheet('Data');

        $this->expectException(WriteException::class);
        $writer->addRowObject(new Row([new Cell(INF, CellType::Number)]));
    }

    public function testDuplicateSheetNamesAreComparedCaseInsensitively(): void
    {
        $writer = Spreadsheet::writer($this->newPath());
        $writer->startSheet('Data');
        $writer->addRow(['x']);

        $this->expectException(WriteException::class);
        $this->expectExceptionMessage('Duplicate sheet name');
        $writer->startSheet('DATA');
    }

    public function testInlineCellTypeBypassesSharedStrings(): void
    {
        $path = $this->newPath();
        $writer = Spreadsheet::writer($path, new WriteOptions(useSharedStrings: true));
        $writer->startSheet('Data');
        $writer->addRowObject(new Row([
            new Cell('keep-me-inline', CellType::Inline),
            new Cell('share-me', CellType::String),
        ]));
        $writer->close();

        $sheet = $this->member($path, 'xl/worksheets/sheet1.xml');
        self::assertStringContainsString(
            't="inlineStr"><is><t xml:space="preserve">keep-me-inline</t></is>',
            $sheet,
        );
        self::assertStringContainsString('t="s"', $sheet);

        // Round-trip is unaffected by where each string lives.
        $reader = Spreadsheet::reader($path);
        $rows = [];
        foreach ($reader->values() as $row) {
            $rows[] = $row;
        }
        self::assertSame([['keep-me-inline', 'share-me']], $rows);
        $reader->close();
    }

    public function testErrorCellsRoundTripTyped(): void
    {
        $path = $this->newPath();
        $writer = Spreadsheet::writer($path);
        $writer->startSheet('Data');
        $writer->addRowObject(new Row([
            new Cell('#DIV/0!', CellType::Error),
            new Cell('#DIV/0!', CellType::String), // same text as literal content
        ]));
        $writer->close();

        self::assertStringContainsString('t="e"><v>#DIV/0!</v>', $this->member($path, 'xl/worksheets/sheet1.xml'));

        $reader = Spreadsheet::reader($path);
        $cells = null;
        foreach ($reader->rows() as $row) {
            $cells = $row;
        }
        self::assertNotNull($cells);
        self::assertSame(CellType::Error, $cells[0]->type);
        self::assertSame('#DIV/0!', $cells[0]->value);
        self::assertSame(CellType::String, $cells[1]->type);
        $reader->close();
    }

    public function testReversedRangesAreNormalized(): void
    {
        $path = $this->newPath();
        $writer = Spreadsheet::writer($path);
        $writer->startSheet('Data');
        $writer->addRow(['x']);
        $writer->mergeCells('D3:A1');
        $writer->autoFilter('C1:A1');
        $writer->close();

        $sheet = $this->member($path, 'xl/worksheets/sheet1.xml');
        self::assertStringContainsString('<mergeCell ref="A1:D3"/>', $sheet);
        self::assertStringContainsString('<autoFilter ref="A1:C1"/>', $sheet);
    }

    public function testDefinedNamesThatAreCellReferencesAreRejected(): void
    {
        $writer = Spreadsheet::writer($this->newPath());
        $writer->startSheet('Data');
        $writer->addRow(['x']);

        foreach (['A1', 'XFD1048576', 'R1C1', 'RC', 'r', 'C'] as $name) {
            try {
                $writer->defineName($name, 'Data!$A$1');
                self::fail(sprintf('Cell-reference name "%s" was accepted.', $name));
            } catch (WriteException $e) {
                self::assertStringContainsString('cell reference', $e->getMessage());
            }
        }

        // A normal name still works.
        $writer->defineName('Sales_2024', 'Data!$A$1');
        $writer->close();
    }

    public function testAbandonedWriterLeavesNoTempDirectories(): void
    {
        $countTempDirs = static function (): int {
            $dirs = glob(sys_get_temp_dir() . '/phpdot_sheets_*');

            return $dirs === false ? 0 : count($dirs);
        };
        $before = $countTempDirs();

        (function (): void {
            $writer = Spreadsheet::writer($this->newPath());
            $writer->startSheet('Data');
            $writer->addRow(['x']);
            // No close() — simulates an exception in calling code mid-write.
        })();
        gc_collect_cycles();

        self::assertSame($before, $countTempDirs());
    }

    private function newPath(): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'sheets_hard_');
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
            self::fail(sprintf('Part not found: %s', $name));
        }

        return $data;
    }
}
