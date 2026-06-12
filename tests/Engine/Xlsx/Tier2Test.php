<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Tests\Engine\Xlsx;

use PHPdot\Sheets\Engine\Model\Color;
use PHPdot\Sheets\Engine\Model\SheetOptions;
use PHPdot\Sheets\Engine\Model\WriteOptions;
use PHPdot\Sheets\Engine\Support\WriteException;
use PHPdot\Sheets\Spreadsheet;
use PHPUnit\Framework\TestCase;

final class Tier2Test extends TestCase
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

    public function testHyperlinkWiresTrailerAndExternalRelationship(): void
    {
        $path = $this->newPath();
        $writer = Spreadsheet::writer($path);
        $writer->startSheet('Links');
        $writer->addRow(['Visit us']);
        $writer->hyperlink('A1', 'https://phpdot.com', 'Open site');
        $writer->close();

        $sheet = $this->member($path, 'xl/worksheets/sheet1.xml');
        if (preg_match('/<hyperlink ref="A1" r:id="(rId\d+)" tooltip="Open site"\/>/', $sheet, $m) !== 1) {
            self::fail('Hyperlink element missing.');
        }
        self::assertGreaterThan(strpos($sheet, '</sheetData>'), strpos($sheet, '<hyperlinks>'));

        $rels = $this->member($path, 'xl/worksheets/_rels/sheet1.xml.rels');
        self::assertStringContainsString('Id="' . $m[1] . '"', $rels);
        self::assertStringContainsString('Target="https://phpdot.com" TargetMode="External"', $rels);
        self::assertInstanceOf(\SimpleXMLElement::class, simplexml_load_string($sheet));
    }

    public function testInvalidHyperlinkCellThrows(): void
    {
        $path = $this->newPath();
        $writer = Spreadsheet::writer($path);
        $writer->startSheet('Links');

        $this->expectException(WriteException::class);
        $writer->hyperlink('A1:B2', 'https://x.com');
    }

    public function testAutoFilter(): void
    {
        $path = $this->newPath();
        $writer = Spreadsheet::writer($path);
        $writer->startSheet('Data');
        $writer->addRow(['A', 'B', 'C']);
        $writer->autoFilter('A1:C1');
        $writer->close();

        $sheet = $this->member($path, 'xl/worksheets/sheet1.xml');
        self::assertStringContainsString('<autoFilter ref="A1:C1"/>', $sheet);
        self::assertGreaterThan(strpos($sheet, '</sheetData>'), strpos($sheet, '<autoFilter'));
    }

    public function testTrailersOrderedAutoFilterMergeHyperlink(): void
    {
        $path = $this->newPath();
        $writer = Spreadsheet::writer($path);
        $writer->startSheet('Data');
        $writer->addRow(['Title', 'B', 'C']);
        // Added scrambled — emitted in CT_Worksheet order.
        $writer->hyperlink('A2', 'https://x.com');
        $writer->mergeCells('A1:C1');
        $writer->autoFilter('A2:C2');
        $writer->close();

        $sheet = $this->member($path, 'xl/worksheets/sheet1.xml');
        $autoFilter = strpos($sheet, '<autoFilter');
        $merge = strpos($sheet, '<mergeCells');
        $links = strpos($sheet, '<hyperlinks');
        if ($autoFilter === false || $merge === false || $links === false) {
            self::fail('A trailer is missing.');
        }
        self::assertGreaterThan($autoFilter, $merge);
        self::assertGreaterThan($merge, $links);
    }

    public function testDocumentProperties(): void
    {
        $path = $this->newPath();
        $writer = Spreadsheet::writer(
            $path,
            new WriteOptions(creator: 'Omar', title: 'Q1 Report', subject: 'Sales', keywords: 'sales,q1'),
        );
        $writer->startSheet('Data');
        $writer->addRow(['x']);
        $writer->close();

        $core = $this->member($path, 'docProps/core.xml');
        self::assertStringContainsString('<dc:creator>Omar</dc:creator>', $core);
        self::assertStringContainsString('<dc:title>Q1 Report</dc:title>', $core);
        self::assertStringContainsString('<dc:subject>Sales</dc:subject>', $core);
        self::assertStringContainsString('<cp:keywords>sales,q1</cp:keywords>', $core);
    }

    public function testNamedRange(): void
    {
        $path = $this->newPath();
        $writer = Spreadsheet::writer($path);
        $writer->startSheet('Data');
        $writer->addRow(['x']);
        $writer->defineName('SalesData', 'Data!$A$1:$A$9');
        $writer->close();

        $workbook = $this->member($path, 'xl/workbook.xml');
        self::assertStringContainsString(
            '<definedNames><definedName name="SalesData">Data!$A$1:$A$9</definedName></definedNames>',
            $workbook,
        );
    }

    public function testInvalidDefinedNameThrows(): void
    {
        $path = $this->newPath();
        $writer = Spreadsheet::writer($path);
        $writer->startSheet('Data');

        $this->expectException(WriteException::class);
        $writer->defineName('has spaces', 'Data!$A$1');
    }

    public function testTabColorAndHiddenSheet(): void
    {
        $path = $this->newPath();
        $writer = Spreadsheet::writer($path);
        $writer->startSheet('Visible', new SheetOptions(tabColor: Color::hex('#FF0000')));
        $writer->addRow(['x']);
        $writer->startSheet('Secret', new SheetOptions(hidden: true));
        $writer->addRow(['y']);
        $writer->close();

        $sheet1 = $this->member($path, 'xl/worksheets/sheet1.xml');
        self::assertStringContainsString('<sheetPr><tabColor rgb="FFFF0000"/></sheetPr>', $sheet1);
        self::assertLessThan(strpos($sheet1, '<sheetData>'), strpos($sheet1, '<sheetPr>'));

        $workbook = $this->member($path, 'xl/workbook.xml');
        self::assertStringContainsString('name="Secret" sheetId="2" state="hidden"', $workbook);
    }

    private function newPath(): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'sheets_tier2_');
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
