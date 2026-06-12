<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Tests\Engine\Feature\Image;

use PHPdot\Sheets\Engine\Feature\Image\ImageNode;
use PHPdot\Sheets\Engine\Feature\Image\ImagePlugin;
use PHPdot\Sheets\Spreadsheet;
use PHPUnit\Framework\TestCase;

final class ImageTest extends TestCase
{
    private const PNG = "\x89PNG\r\n\x1a\nfake-bytes";

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

    public function testSingleImageWiresMediaDrawingAndWorksheetRelChain(): void
    {
        $path = $this->newPath();
        $writer = Spreadsheet::writer($path)->use(new ImagePlugin());
        $writer->startSheet('Pics');
        $writer->addRow(['header']);
        $writer->add(new ImageNode(self::PNG, 'png', 2, 1, 100, 50));
        $writer->close();

        // Media bytes preserved.
        self::assertSame(self::PNG, $this->member($path, 'xl/media/image1.png'));

        // DrawingML: one anchor, correct cell + EMU size (px * 9525).
        $drawing = $this->member($path, 'xl/drawings/drawing1.xml');
        self::assertInstanceOf(\SimpleXMLElement::class, simplexml_load_string($drawing));
        self::assertStringContainsString('<xdr:col>2</xdr:col>', $drawing);
        self::assertStringContainsString('<xdr:row>1</xdr:row>', $drawing);
        self::assertStringContainsString('cx="952500"', $drawing);
        self::assertStringContainsString('cy="476250"', $drawing);

        // drawing -> media relationship resolves to the embed used in the anchor.
        $drawingRels = $this->member($path, 'xl/drawings/_rels/drawing1.xml.rels');
        if (preg_match('/r:embed="(rId\d+)"/', $drawing, $embed) !== 1) {
            self::fail('Drawing has no image embed relationship.');
        }
        self::assertStringContainsString('Id="' . $embed[1] . '"', $drawingRels);
        self::assertStringContainsString('Target="../media/image1.png"', $drawingRels);

        // worksheet -> drawing relationship resolves to the <drawing r:id> trailer.
        $sheet = $this->member($path, 'xl/worksheets/sheet1.xml');
        self::assertInstanceOf(\SimpleXMLElement::class, simplexml_load_string($sheet));
        if (preg_match('/<drawing r:id="(rId\d+)"\/>/', $sheet, $draw) !== 1) {
            self::fail('Worksheet has no drawing relationship.');
        }
        $worksheetRels = $this->member($path, 'xl/worksheets/_rels/sheet1.xml.rels');
        self::assertStringContainsString('Id="' . $draw[1] . '"', $worksheetRels);
        self::assertStringContainsString('Target="../drawings/drawing1.xml"', $worksheetRels);

        // The drawing trailer sits AFTER </sheetData> (CT_Worksheet order).
        self::assertGreaterThan(
            strpos($sheet, '</sheetData>'),
            strpos($sheet, '<drawing'),
        );

        // Content types declare the image extension and the drawing part.
        $contentTypes = $this->member($path, '[Content_Types].xml');
        self::assertStringContainsString('Extension="png"', $contentTypes);
        self::assertStringContainsString('/xl/drawings/drawing1.xml', $contentTypes);
    }

    public function testMultipleImagesOnOneSheetAggregateIntoOneDrawing(): void
    {
        $path = $this->newPath();
        $writer = Spreadsheet::writer($path)->use(new ImagePlugin());
        $writer->startSheet('Pics');
        $writer->add(new ImageNode(self::PNG, 'png', 0, 0, 10, 10));
        $writer->add(new ImageNode(self::PNG, 'png', 3, 3, 20, 20));
        $writer->close();

        // One drawing part, two anchors.
        $drawing = $this->member($path, 'xl/drawings/drawing1.xml');
        self::assertSame(2, substr_count($drawing, '<xdr:oneCellAnchor>'));
        self::assertFalse($this->hasMember($path, 'xl/drawings/drawing2.xml'));

        // Two distinct media parts, two embed rels.
        self::assertTrue($this->hasMember($path, 'xl/media/image1.png'));
        self::assertTrue($this->hasMember($path, 'xl/media/image2.png'));
        $drawingRels = $this->member($path, 'xl/drawings/_rels/drawing1.xml.rels');
        self::assertSame(2, substr_count($drawingRels, '/relationships/image'));

        // Exactly one worksheet <drawing>.
        $sheet = $this->member($path, 'xl/worksheets/sheet1.xml');
        self::assertSame(1, substr_count($sheet, '<drawing r:id='));
    }

    public function testImageNodeIsSkippedWhenPluginNotEnabled(): void
    {
        $path = $this->newPath();
        $writer = Spreadsheet::writer($path); // no ->use(new ImagePlugin())
        $writer->startSheet('Pics');
        $writer->addRow(['header']);
        $writer->add(new ImageNode(self::PNG, 'png', 0, 0, 10, 10));
        $writer->close();

        $sheet = $this->member($path, 'xl/worksheets/sheet1.xml');
        self::assertInstanceOf(\SimpleXMLElement::class, simplexml_load_string($sheet));
        self::assertStringNotContainsString('<drawing', $sheet);
        self::assertFalse($this->hasMember($path, 'xl/drawings/drawing1.xml'));
        self::assertFalse($this->hasMember($path, 'xl/media/image1.png'));
    }

    private function newPath(): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'sheets_img_');
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
