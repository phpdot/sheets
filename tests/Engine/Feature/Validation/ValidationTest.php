<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Tests\Engine\Feature\Validation;

use PHPdot\Sheets\Engine\Feature\Image\ImageNode;
use PHPdot\Sheets\Engine\Feature\Image\ImagePlugin;
use PHPdot\Sheets\Engine\Feature\Validation\CfOperator;
use PHPdot\Sheets\Engine\Feature\Validation\ConditionalFormatNode;
use PHPdot\Sheets\Engine\Feature\Validation\DataValidationNode;
use PHPdot\Sheets\Engine\Feature\Validation\ValidationPlugin;
use PHPdot\Sheets\Engine\Model\Color;
use PHPdot\Sheets\Engine\Model\Style;
use PHPdot\Sheets\Spreadsheet;
use PHPUnit\Framework\TestCase;

final class ValidationTest extends TestCase
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

    public function testListDataValidationProducesDropdown(): void
    {
        $path = $this->newPath();
        $writer = Spreadsheet::writer($path)->use(new ValidationPlugin());
        $writer->startSheet('Data');
        $writer->addRow(['Choice']);
        $writer->add(DataValidationNode::list('A2:A10', ['Yes', 'No', 'Maybe']));
        $writer->close();

        $sheet = $this->member($path, 'xl/worksheets/sheet1.xml');
        self::assertInstanceOf(\SimpleXMLElement::class, simplexml_load_string($sheet));
        self::assertStringContainsString('<dataValidations count="1">', $sheet);
        self::assertStringContainsString('type="list"', $sheet);
        self::assertStringContainsString('sqref="A2:A10"', $sheet);
        self::assertStringContainsString('<formula1>"Yes,No,Maybe"</formula1>', $sheet);
    }

    public function testMultipleDataValidationsAggregateIntoOneContainer(): void
    {
        $path = $this->newPath();
        $writer = Spreadsheet::writer($path)->use(new ValidationPlugin());
        $writer->startSheet('Data');
        $writer->add(DataValidationNode::list('A2:A10', ['Yes', 'No']));
        $writer->add(DataValidationNode::list('B2:B10', ['Lo', 'Hi']));
        $writer->close();

        $sheet = $this->member($path, 'xl/worksheets/sheet1.xml');
        self::assertSame(1, substr_count($sheet, '<dataValidations count='));
        self::assertStringContainsString('<dataValidations count="2">', $sheet);
        self::assertSame(2, substr_count($sheet, '<dataValidation '));
    }

    public function testConditionalFormatRegistersDxfAndAddsRule(): void
    {
        $path = $this->newPath();
        $style = (new Style())->withBackgroundColor(Color::hex('#FFFF00'));
        $writer = Spreadsheet::writer($path)->use(new ValidationPlugin());
        $writer->startSheet('Data');
        $writer->addRow(['Score']);
        $writer->add(new ConditionalFormatNode('A2:A10', CfOperator::GreaterThan, '100', $style));
        $writer->close();

        // The differential format is registered in styles.xml (dxf uses bgColor).
        $styles = $this->member($path, 'xl/styles.xml');
        self::assertStringContainsString('<dxfs count="1">', $styles);
        self::assertStringContainsString('<bgColor rgb="FFFFFF00"/>', $styles);

        // The rule references that dxf.
        $sheet = $this->member($path, 'xl/worksheets/sheet1.xml');
        self::assertStringContainsString('<conditionalFormatting sqref="A2:A10">', $sheet);
        self::assertStringContainsString('type="cellIs" dxfId="0"', $sheet);
        self::assertStringContainsString('operator="greaterThan"', $sheet);
        self::assertStringContainsString('<formula>100</formula>', $sheet);
    }

    public function testTrailersAreEmittedInCanonicalOrderRegardlessOfAddOrder(): void
    {
        $path = $this->newPath();
        $style = (new Style())->withBackgroundColor(Color::hex('#FFFF00'));
        $writer = Spreadsheet::writer($path)->use(new ImagePlugin(), new ValidationPlugin());
        $writer->startSheet('Data');
        $writer->addRow(['x', 1]);
        // Scrambled add order: drawing, then data validation, then conditional formatting.
        $writer->add(new ImageNode("\x89PNGfake", 'png', 0, 5, 16, 16));
        $writer->add(DataValidationNode::list('A2:A10', ['Yes', 'No']));
        $writer->add(new ConditionalFormatNode('B2:B10', CfOperator::GreaterThan, '100', $style));
        $writer->close();

        $sheet = $this->member($path, 'xl/worksheets/sheet1.xml');
        self::assertInstanceOf(\SimpleXMLElement::class, simplexml_load_string($sheet));

        $sheetData = strpos($sheet, '</sheetData>');
        $cf = strpos($sheet, '<conditionalFormatting');
        $dv = strpos($sheet, '<dataValidations');
        $drawing = strpos($sheet, '<drawing');
        if ($sheetData === false || $cf === false || $dv === false || $drawing === false) {
            self::fail('A required trailer or the sheetData boundary is missing.');
        }

        // CT_Worksheet xsd:sequence: …conditionalFormatting → dataValidations → … → drawing.
        self::assertGreaterThan($sheetData, $cf);
        self::assertGreaterThan($cf, $dv);
        self::assertGreaterThan($dv, $drawing);
    }

    public function testValidationNodesSkippedWhenPluginNotEnabled(): void
    {
        $path = $this->newPath();
        $writer = Spreadsheet::writer($path); // no ->use(new ValidationPlugin())
        $writer->startSheet('Data');
        $writer->addRow(['x']);
        $writer->add(DataValidationNode::list('A2:A10', ['Yes', 'No']));
        $writer->close();

        $sheet = $this->member($path, 'xl/worksheets/sheet1.xml');
        self::assertStringNotContainsString('<dataValidation', $sheet);
        self::assertInstanceOf(\SimpleXMLElement::class, simplexml_load_string($sheet));
    }

    private function newPath(): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'sheets_val_');
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
