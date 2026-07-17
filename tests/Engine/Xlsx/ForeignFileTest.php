<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Tests\Engine\Xlsx;

use PHPdot\Sheets\Engine\Model\CellType;
use PHPdot\Sheets\Engine\Model\SheetInfo;
use PHPdot\Sheets\Engine\Support\ExcelDate;
use PHPdot\Sheets\Engine\Support\ReadException;
use PHPdot\Sheets\Spreadsheet;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the reader against a hand-built file that our own writer would never
 * produce, to validate the two structural fixes from the streamsheet review on
 * realistic foreign input:
 *
 *   1. Worksheet parts resolved via rels (here: non-positional `data1/data2.xml`
 *      with the workbook's FIRST sheet mapped to `data2.xml`) — a positional
 *      `sheetN.xml` reader would read the wrong sheet or none.
 *   2. Shared strings (`t="s"`) resolved by index, including rich-text runs.
 */
final class ForeignFileTest extends TestCase
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

    public function testResolvesSheetsViaRelsAndSharedStrings(): void
    {
        $path = $this->newPath();
        $this->buildForeignXlsx($path);

        $reader = Spreadsheet::reader($path);

        // Sheet order/names come from workbook.xml, not file names.
        $names = array_map(static fn(SheetInfo $s): string => $s->name, $reader->sheets());
        self::assertSame(['First', 'Second'], $names);

        // Sheet 0 (rId1 -> worksheets/data2.xml) with shared strings #0 and #2 (rich run).
        self::assertSame(
            [['first-sheet-value', 'rich-run']],
            $this->collect($reader->values(0)),
        );

        // Sheet 1 (rId2 -> worksheets/data1.xml) with shared string #1.
        self::assertSame(
            [['second-sheet-value']],
            $this->collect($reader->values(1)),
        );

        $reader->close();
    }

    public function testResolvesAbsoluteWorksheetTargets(): void
    {
        // OPC permits package-root-absolute targets; Excel emits relative ones,
        // but other producers legally write "/xl/…".
        $path = $this->newPath();
        $this->buildMinimalXlsx($path, '/xl/worksheets/sheet1.xml');

        self::assertSame([['r1'], ['r2'], ['r3']], $this->collect(Spreadsheet::reader($path)->values()));
    }

    public function testResolvesParentRelativeWorksheetTargets(): void
    {
        $path = $this->newPath();
        $this->buildMinimalXlsx($path, '../xl/worksheets/sheet1.xml');

        self::assertSame([['r1'], ['r2'], ['r3']], $this->collect(Spreadsheet::reader($path)->values()));
    }

    public function testThrowsWhenAReferencedWorksheetPartIsMissing(): void
    {
        // A part the workbook references but the archive lacks is a corrupt
        // file — it must throw, never read as an empty workbook.
        $path = $this->newPath();
        $this->buildMinimalXlsx($path, 'worksheets/not-there.xml');

        $reader = Spreadsheet::reader($path);
        $this->expectException(ReadException::class);
        $this->expectExceptionMessage('not-there.xml');
        foreach ($reader->values() as $ignored) {
            self::fail('Iteration must throw before yielding any row.');
        }
    }

    public function testRowsWithoutRAttributeGetSequentialNumbers(): void
    {
        // The r attribute is optional; several writers omit it. Each such row
        // is the one after its predecessor — not row 1 over and over.
        $path = $this->newPath();
        $this->buildMinimalXlsx($path, 'worksheets/sheet1.xml');

        $keys = [];
        foreach (Spreadsheet::reader($path)->values() as $rowNumber => $row) {
            $keys[] = $rowNumber;
        }
        self::assertSame([1, 2, 3], $keys);
        self::assertCount(3, iterator_to_array(Spreadsheet::reader($path)->values()));
    }

    public function testDimensionHintIsExposed(): void
    {
        $path = $this->newPath();
        $this->buildMinimalXlsx($path, 'worksheets/sheet1.xml');

        self::assertSame('A1:A3', Spreadsheet::reader($path)->sheets()[0]->dimension);
    }

    public function testErrorCellsAreTypedAndDistinguishableFromLiteralText(): void
    {
        $main = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $path = $this->newPath();
        $this->buildMinimalXlsx($path, 'worksheets/sheet1.xml', '<?xml version="1.0"?><worksheet xmlns="' . $main . '"><sheetData>'
            . '<row r="1"><c r="A1" t="e"><v>#DIV/0!</v></c>'
            . '<c r="B1" t="inlineStr"><is><t>#DIV/0!</t></is></c></row>'
            . '</sheetData></worksheet>');

        $cells = null;
        foreach (Spreadsheet::reader($path)->rows() as $row) {
            $cells = $row;
        }
        self::assertNotNull($cells);
        self::assertSame(CellType::Error, $cells[0]->type);
        self::assertSame('#DIV/0!', $cells[0]->value);
        self::assertSame(CellType::String, $cells[1]->type);
    }

    public function testBuiltinDateNumberFormatTypesCellsAsDates(): void
    {
        // Built-in numFmtId 14 (mm-dd-yy) has no <numFmt> entry in styles.xml —
        // the id range alone marks the style as a date.
        $main = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $path = $this->newPath();
        $this->buildMinimalXlsx(
            $path,
            'worksheets/sheet1.xml',
            '<?xml version="1.0"?><worksheet xmlns="' . $main . '"><sheetData>'
            . '<row r="1"><c r="A1" s="1"><v>45000</v></c><c r="B1"><v>45000</v></c></row>'
            . '</sheetData></worksheet>',
            '<?xml version="1.0"?><styleSheet xmlns="' . $main . '">'
            . '<fonts count="1"><font/></fonts><fills count="1"><fill/></fills>'
            . '<borders count="1"><border/></borders>'
            . '<cellXfs count="2"><xf numFmtId="0"/><xf numFmtId="14" applyNumberFormat="1"/></cellXfs>'
            . '</styleSheet>',
        );

        $cells = null;
        foreach (Spreadsheet::reader($path)->rows() as $row) {
            $cells = $row;
        }
        self::assertNotNull($cells);
        self::assertSame(CellType::Date, $cells[0]->type);
        self::assertSame(45000, $cells[0]->value);
        self::assertSame(CellType::Number, $cells[1]->type);
    }

    public function testMacDateSystemSerialsAreNormalizedTo1900(): void
    {
        // 1904-system serial 43844 = 2024-01-15 (1900-system 45306). Date cells
        // must come back in the 1900 system; plain numbers must NOT be offset.
        $main = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $odoc = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
        $path = $this->newPath();
        $this->buildMinimalXlsx(
            $path,
            'worksheets/sheet1.xml',
            '<?xml version="1.0"?><worksheet xmlns="' . $main . '"><sheetData>'
            . '<row r="1"><c r="A1" s="1"><v>43844</v></c><c r="B1"><v>43844</v></c></row>'
            . '</sheetData></worksheet>',
            $this->dateStylesXml(),
            '<?xml version="1.0"?><workbook xmlns="' . $main . '" xmlns:r="' . $odoc . '">'
            . '<workbookPr date1904="1"/>'
            . '<sheets><sheet name="Only" sheetId="1" r:id="rId1"/></sheets></workbook>',
        );

        $cells = null;
        foreach (Spreadsheet::reader($path)->rows() as $row) {
            $cells = $row;
        }
        self::assertNotNull($cells);
        self::assertSame(CellType::Date, $cells[0]->type);
        self::assertSame(45306, $cells[0]->value);
        self::assertSame('2024-01-15', ExcelDate::toDateTime(45306.0)->format('Y-m-d'));
        self::assertSame(CellType::Number, $cells[1]->type);
        self::assertSame(43844, $cells[1]->value); // unstyled number: raw, no offset
    }

    public function testIsoDateCellsAreParsedToSerials(): void
    {
        // Strict-OOXML t="d" cells hold ISO 8601 text, not serials.
        $main = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $path = $this->newPath();
        $this->buildMinimalXlsx($path, 'worksheets/sheet1.xml', '<?xml version="1.0"?><worksheet xmlns="' . $main . '"><sheetData>'
            . '<row r="1"><c r="A1" t="d"><v>2024-01-15T00:00:00</v></c>'
            . '<c r="B1" t="d"><v>certainly not a date</v></c></row>'
            . '</sheetData></worksheet>');

        $cells = null;
        foreach (Spreadsheet::reader($path)->rows() as $row) {
            $cells = $row;
        }
        self::assertNotNull($cells);
        self::assertSame(CellType::Date, $cells[0]->type);
        self::assertSame(45306.0, $cells[0]->value);
        self::assertSame(CellType::String, $cells[1]->type);
        self::assertSame('certainly not a date', $cells[1]->value); // unparseable: raw text preserved
    }

    private function dateStylesXml(): string
    {
        $main = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

        return '<?xml version="1.0"?><styleSheet xmlns="' . $main . '">'
            . '<fonts count="1"><font/></fonts><fills count="1"><fill/></fills>'
            . '<borders count="1"><border/></borders>'
            . '<cellXfs count="2"><xf numFmtId="0"/><xf numFmtId="14" applyNumberFormat="1"/></cellXfs>'
            . '</styleSheet>';
    }

    /**
     * A minimal one-sheet archive whose workbook rel uses the given target —
     * three inline-string rows without r attributes, with a dimension hint.
     */
    private function buildMinimalXlsx(string $path, string $target, ?string $sheetXml = null, ?string $stylesXml = null, ?string $workbookXml = null): void
    {
        $main = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $ct = 'http://schemas.openxmlformats.org/package/2006/content-types';
        $rel = 'http://schemas.openxmlformats.org/package/2006/relationships';
        $odoc = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
        $head = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';

        $parts = [
            '[Content_Types].xml' => $head . '<Types xmlns="' . $ct . '">'
                . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                . '<Default Extension="xml" ContentType="application/xml"/>'
                . '</Types>',
            '_rels/.rels' => $head . '<Relationships xmlns="' . $rel . '">'
                . '<Relationship Id="rId1" Type="' . $odoc . '/officeDocument" Target="xl/workbook.xml"/>'
                . '</Relationships>',
            'xl/workbook.xml' => $workbookXml ?? $head . '<workbook xmlns="' . $main . '" xmlns:r="' . $odoc . '">'
                . '<sheets><sheet name="Only" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => $head . '<Relationships xmlns="' . $rel . '">'
                . '<Relationship Id="rId1" Type="' . $odoc . '/worksheet" Target="' . $target . '"/>'
                . '</Relationships>',
            'xl/worksheets/sheet1.xml' => $sheetXml ?? $head . '<worksheet xmlns="' . $main . '">'
                . '<dimension ref="A1:A3"/><sheetData>'
                . '<row><c t="inlineStr"><is><t>r1</t></is></c></row>'
                . '<row><c t="inlineStr"><is><t>r2</t></is></c></row>'
                . '<row><c t="inlineStr"><is><t>r3</t></is></c></row>'
                . '</sheetData></worksheet>',
        ];
        if ($stylesXml !== null) {
            $parts['xl/styles.xml'] = $stylesXml;
        }

        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            self::fail('Cannot create the foreign test archive.');
        }
        foreach ($parts as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();
    }

    private function buildForeignXlsx(string $path): void
    {
        $main = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $ct = 'http://schemas.openxmlformats.org/package/2006/content-types';
        $rel = 'http://schemas.openxmlformats.org/package/2006/relationships';
        $odoc = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
        $head = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';

        $parts = [
            '[Content_Types].xml' => $head . '<Types xmlns="' . $ct . '">'
                . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                . '<Default Extension="xml" ContentType="application/xml"/>'
                . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                . '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
                . '<Override PartName="/xl/worksheets/data1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                . '<Override PartName="/xl/worksheets/data2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                . '</Types>',
            '_rels/.rels' => $head . '<Relationships xmlns="' . $rel . '">'
                . '<Relationship Id="rId1" Type="' . $odoc . '/officeDocument" Target="xl/workbook.xml"/>'
                . '</Relationships>',
            'xl/workbook.xml' => $head . '<workbook xmlns="' . $main . '" xmlns:r="' . $odoc . '">'
                . '<sheets>'
                . '<sheet name="First" sheetId="1" r:id="rId1"/>'
                . '<sheet name="Second" sheetId="2" r:id="rId2"/>'
                . '</sheets></workbook>',
            // Reversed, non-positional mapping: first sheet -> data2.xml, second -> data1.xml.
            'xl/_rels/workbook.xml.rels' => $head . '<Relationships xmlns="' . $rel . '">'
                . '<Relationship Id="rId1" Type="' . $odoc . '/worksheet" Target="worksheets/data2.xml"/>'
                . '<Relationship Id="rId2" Type="' . $odoc . '/worksheet" Target="worksheets/data1.xml"/>'
                . '<Relationship Id="rId3" Type="' . $odoc . '/sharedStrings" Target="sharedStrings.xml"/>'
                . '</Relationships>',
            'xl/sharedStrings.xml' => $head . '<sst xmlns="' . $main . '" count="4" uniqueCount="3">'
                . '<si><t>first-sheet-value</t></si>'
                . '<si><t>second-sheet-value</t></si>'
                . '<si><r><t>rich</t></r><r><t>-run</t></r></si>'
                . '</sst>',
            'xl/worksheets/data2.xml' => $head . '<worksheet xmlns="' . $main . '"><sheetData>'
                . '<row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>2</v></c></row>'
                . '</sheetData></worksheet>',
            'xl/worksheets/data1.xml' => $head . '<worksheet xmlns="' . $main . '"><sheetData>'
                . '<row r="1"><c r="A1" t="s"><v>1</v></c></row>'
                . '</sheetData></worksheet>',
        ];

        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            self::fail('Cannot create the foreign test archive.');
        }
        foreach ($parts as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();
    }

    /**
     * @param iterable<int, list<int|float|string|bool|null>> $rows
     *
     * @return list<list<int|float|string|bool|null>>
     */
    private function collect(iterable $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[] = $row;
        }

        return $out;
    }

    private function newPath(): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'sheets_foreign_');
        $this->tempFiles[] = $path;

        return $path;
    }
}
