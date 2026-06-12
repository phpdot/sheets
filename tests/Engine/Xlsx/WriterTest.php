<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Tests\Engine\Xlsx;

use PHPdot\Sheets\Engine\Model\Cell;
use PHPdot\Sheets\Engine\Model\CellType;
use PHPdot\Sheets\Engine\Model\Row;
use PHPdot\Sheets\Engine\Model\Style;
use PHPdot\Sheets\Engine\Support\WriteException;
use PHPdot\Sheets\Spreadsheet;
use PHPUnit\Framework\TestCase;

final class WriterTest extends TestCase
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

    public function testProducesValidArchiveWithExpectedParts(): void
    {
        $path = $this->newPath();
        $writer = Spreadsheet::writer($path);
        $writer->startSheet('Sales');
        $writer->addRow(['Product', 'Qty', 'Price']);
        $writer->addRow(['Widget', 42, 9.99]);
        $writer->close();

        self::assertFileExists($path);
        self::assertNotSame('', $this->member($path, '[Content_Types].xml'));
        self::assertNotSame('', $this->member($path, '_rels/.rels'));
        self::assertNotSame('', $this->member($path, 'xl/workbook.xml'));
        self::assertNotSame('', $this->member($path, 'xl/_rels/workbook.xml.rels'));
        self::assertNotSame('', $this->member($path, 'xl/styles.xml'));
        self::assertNotSame('', $this->member($path, 'xl/worksheets/sheet1.xml'));
    }

    public function testSheetXmlIsWellFormedWithTypedValues(): void
    {
        $path = $this->newPath();
        $writer = Spreadsheet::writer($path);
        $writer->startSheet('Data');
        $writer->addRow(['Widget', 42, 9.99, true, null]);
        $writer->close();

        $sheet = $this->member($path, 'xl/worksheets/sheet1.xml');
        self::assertInstanceOf(\SimpleXMLElement::class, simplexml_load_string($sheet));
        self::assertStringContainsString('<v>42</v>', $sheet);
        self::assertStringContainsString('<v>9.99</v>', $sheet);
        self::assertStringContainsString('t="b"><v>1</v>', $sheet);
        self::assertStringContainsString('xml:space="preserve">Widget', $sheet);

        $workbook = $this->member($path, 'xl/workbook.xml');
        self::assertStringContainsString('name="Data"', $workbook);
        self::assertStringContainsString('fullCalcOnLoad="1"', $workbook);
    }

    public function testControlCharactersAreSanitizedSoFileStaysValid(): void
    {
        $path = $this->newPath();
        $writer = Spreadsheet::writer($path);
        $writer->startSheet('Data');
        $writer->addRow(["AB\x01\x1FCD"]);
        $writer->close();

        $sheet = $this->member($path, 'xl/worksheets/sheet1.xml');
        self::assertStringNotContainsString("\x01", $sheet);
        self::assertInstanceOf(\SimpleXMLElement::class, simplexml_load_string($sheet));
        self::assertStringContainsString('>ABCD<', $sheet);
    }

    public function testRegisteredStyleIsAppliedAndSerialized(): void
    {
        $path = $this->newPath();
        $writer = Spreadsheet::writer($path);
        $boldId = $writer->registerStyle((new Style())->withBold());
        self::assertSame($boldId, $writer->registerStyle((new Style())->withBold())); // dedup
        $writer->startSheet('Data');
        $writer->addRow(['Header'], $boldId);
        $writer->close();

        $sheet = $this->member($path, 'xl/worksheets/sheet1.xml');
        $styles = $this->member($path, 'xl/styles.xml');
        self::assertStringContainsString('s="' . $boldId . '"', $sheet);
        self::assertStringContainsString('<b/>', $styles);
        self::assertStringContainsString('patternType="gray125"', $styles); // reserved fill 1 present
    }

    public function testRichRowObjectWithFormulaAndDate(): void
    {
        $path = $this->newPath();
        $writer = Spreadsheet::writer($path);
        $writer->startSheet('Data');
        $writer->addRowObject(new Row([
            new Cell('SUM(A1:A2)', CellType::Formula),
            new Cell(44927, CellType::Date),
        ]));
        $writer->close();

        $sheet = $this->member($path, 'xl/worksheets/sheet1.xml');
        self::assertStringContainsString('<f>SUM(A1:A2)</f>', $sheet);
        self::assertStringContainsString('<v>44927</v>', $sheet);
    }

    public function testRejectsIllegalSheetName(): void
    {
        $writer = Spreadsheet::writer($this->newPath());

        $this->expectException(WriteException::class);

        $writer->startSheet('Q1/Q2');
    }

    public function testRejectsTooLongSheetName(): void
    {
        $writer = Spreadsheet::writer($this->newPath());

        $this->expectException(WriteException::class);

        $writer->startSheet(str_repeat('x', 32));
    }

    public function testWriterMemoryStaysBoundedRegardlessOfRowCount(): void
    {
        $path = $this->newPath();

        gc_collect_cycles();
        $before = memory_get_usage();

        $writer = Spreadsheet::writer($path);
        $writer->startSheet('Bench');
        for ($i = 1; $i <= 50000; $i++) {
            $writer->addRow(["User {$i}", "u{$i}@example.com", $i % 100, $i * 1.5, $i % 2 === 0]);
        }
        $writer->close();

        $growth = memory_get_usage() - $before;

        // Streaming: retained memory is independent of row count. Buffering 50k
        // rows would blow far past this. Ceiling is generous to avoid CI flakiness.
        self::assertLessThan(
            4 * 1024 * 1024,
            $growth,
            sprintf('Writer retained %d bytes for 50k rows — memory is not bounded.', $growth),
        );
    }

    private function newPath(): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'sheets_xlsx_');
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
