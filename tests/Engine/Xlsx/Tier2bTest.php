<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Tests\Engine\Xlsx;

use PHPdot\Sheets\Engine\Model\Orientation;
use PHPdot\Sheets\Engine\Model\PageSetup;
use PHPdot\Sheets\Engine\Model\SheetOptions;
use PHPdot\Sheets\Spreadsheet;
use PHPUnit\Framework\TestCase;

final class Tier2bTest extends TestCase
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

    public function testHiddenColumnsAndRows(): void
    {
        $path = $this->newPath();
        $writer = Spreadsheet::writer($path);
        $writer->startSheet('Data', new SheetOptions(columnWidths: [0 => 20], hiddenColumns: [0, 2]));
        $writer->addRow(['visible']);
        $writer->addRow(['hidden row'], null, null, true);
        $writer->close();

        $sheet = $this->member($path, 'xl/worksheets/sheet1.xml');
        // Column A: width + hidden; column C: hidden only.
        self::assertStringContainsString('<col min="1" max="1" width="20" customWidth="1" hidden="1"/>', $sheet);
        self::assertStringContainsString('<col min="3" max="3" hidden="1"/>', $sheet);
        self::assertStringContainsString('<row r="2" hidden="1">', $sheet);
    }

    public function testSheetProtection(): void
    {
        $path = $this->newPath();
        $writer = Spreadsheet::writer($path);
        $writer->startSheet('Locked', new SheetOptions(protectSheet: true, password: 'secret'));
        $writer->addRow(['x']);
        $writer->close();

        $sheet = $this->member($path, 'xl/worksheets/sheet1.xml');
        self::assertMatchesRegularExpression('/<sheetProtection sheet="1" password="[0-9A-F]+"\/>/', $sheet);
        self::assertGreaterThan(strpos($sheet, '</sheetData>'), strpos($sheet, '<sheetProtection'));
    }

    public function testPageSetup(): void
    {
        $path = $this->newPath();
        $writer = Spreadsheet::writer($path);
        $writer->startSheet('Report', new SheetOptions(pageSetup: new PageSetup(
            orientation: Orientation::Landscape,
            fitToWidth: 1,
            header: 'Quarterly Report',
            printArea: 'A1:C10',
            repeatRows: 1,
        )));
        $writer->addRow(['x']);
        $writer->close();

        $sheet = $this->member($path, 'xl/worksheets/sheet1.xml');
        self::assertStringContainsString('<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>', $sheet);
        self::assertStringContainsString('<pageSetup orientation="landscape" fitToWidth="1"/>', $sheet);
        self::assertStringContainsString('<pageMargins ', $sheet);
        self::assertStringContainsString('<oddHeader>&amp;CQuarterly Report</oddHeader>', $sheet);

        $workbook = $this->member($path, 'xl/workbook.xml');
        self::assertStringContainsString('name="_xlnm.Print_Area" localSheetId="0">\'Report\'!$A$1:$C$10', $workbook);
        self::assertStringContainsString('name="_xlnm.Print_Titles" localSheetId="0">\'Report\'!$1:$1', $workbook);
    }

    public function testCellComments(): void
    {
        $path = $this->newPath();
        $writer = Spreadsheet::writer($path);
        $writer->startSheet('Data');
        $writer->addRow(['value']);
        $writer->comment('A1', 'Needs review', 'Omar');
        $writer->close();

        // Comments part.
        $comments = $this->member($path, 'xl/comments1.xml');
        self::assertInstanceOf(\SimpleXMLElement::class, simplexml_load_string($comments));
        self::assertStringContainsString('<author>Omar</author>', $comments);
        self::assertStringContainsString('<comment ref="A1" authorId="0">', $comments);
        self::assertStringContainsString('Needs review', $comments);

        // Legacy VML drawing part.
        $vml = $this->member($path, 'xl/drawings/vmlDrawing1.vml');
        self::assertInstanceOf(\SimpleXMLElement::class, simplexml_load_string($vml));
        self::assertStringContainsString('ObjectType="Note"', $vml);
        self::assertStringContainsString('<x:Row>0</x:Row><x:Column>0</x:Column>', $vml);

        // Worksheet links the legacy drawing; rels and content types wired.
        $sheet = $this->member($path, 'xl/worksheets/sheet1.xml');
        self::assertStringContainsString('<legacyDrawing r:id=', $sheet);
        $rels = $this->member($path, 'xl/worksheets/_rels/sheet1.xml.rels');
        self::assertStringContainsString('/relationships/comments', $rels);
        self::assertStringContainsString('/relationships/vmlDrawing', $rels);
        $contentTypes = $this->member($path, '[Content_Types].xml');
        self::assertStringContainsString('/xl/comments1.xml', $contentTypes);
        self::assertStringContainsString('Extension="vml"', $contentTypes);
    }

    public function testInvalidCommentCellThrows(): void
    {
        $path = $this->newPath();
        $writer = Spreadsheet::writer($path);
        $writer->startSheet('Data');

        $this->expectException(\PHPdot\Sheets\Engine\Support\WriteException::class);
        $writer->comment('ZZ', 'bad cell', null);
    }

    private function newPath(): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'sheets_tier2b_');
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
