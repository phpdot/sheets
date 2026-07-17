<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Tests\Engine\Feature\Validation;

use PHPdot\Sheets\Engine\Feature\Validation\CfOperator;
use PHPdot\Sheets\Engine\Feature\Validation\ColorScaleNode;
use PHPdot\Sheets\Engine\Feature\Validation\ConditionalFormatNode;
use PHPdot\Sheets\Engine\Feature\Validation\DataBarNode;
use PHPdot\Sheets\Engine\Feature\Validation\DuplicateValuesNode;
use PHPdot\Sheets\Engine\Feature\Validation\ExpressionFormatNode;
use PHPdot\Sheets\Engine\Feature\Validation\IconSet;
use PHPdot\Sheets\Engine\Feature\Validation\IconSetNode;
use PHPdot\Sheets\Engine\Feature\Validation\ValidationPlugin;
use PHPdot\Sheets\Engine\Model\Color;
use PHPdot\Sheets\Engine\Model\Style;
use PHPdot\Sheets\Spreadsheet;
use PHPUnit\Framework\TestCase;

final class ConditionalFormatVisualTest extends TestCase
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

    public function testDataBar(): void
    {
        $sheet = $this->sheetWith(static fn($w) => $w->add(new DataBarNode('B2:B10', Color::hex('#638EC6'))));
        self::assertStringContainsString(
            '<cfRule type="dataBar" priority="1"><dataBar><cfvo type="min"/><cfvo type="max"/><color rgb="FF638EC6"/></dataBar></cfRule>',
            $sheet,
        );
    }

    public function testTwoColorScale(): void
    {
        $sheet = $this->sheetWith(static fn($w) => $w->add(
            new ColorScaleNode('B2:B10', Color::hex('#F8696B'), Color::hex('#63BE7B')),
        ));
        self::assertStringContainsString('<cfRule type="colorScale" priority="1"><colorScale>', $sheet);
        self::assertStringContainsString('<cfvo type="min"/><cfvo type="max"/>', $sheet);
        self::assertStringContainsString('<color rgb="FFF8696B"/><color rgb="FF63BE7B"/>', $sheet);
    }

    public function testThreeColorScale(): void
    {
        $sheet = $this->sheetWith(static fn($w) => $w->add(
            new ColorScaleNode('B2:B10', Color::hex('#F8696B'), Color::hex('#63BE7B'), Color::hex('#FFEB84')),
        ));
        self::assertStringContainsString(
            '<cfvo type="min"/><cfvo type="percentile" val="50"/><cfvo type="max"/>',
            $sheet,
        );
    }

    public function testThreeIconSet(): void
    {
        $sheet = $this->sheetWith(static fn($w) => $w->add(new IconSetNode('B2:B10', IconSet::ThreeTrafficLights)));
        self::assertStringContainsString(
            '<iconSet iconSet="3TrafficLights1"><cfvo type="percent" val="0"/>'
            . '<cfvo type="percent" val="33"/><cfvo type="percent" val="67"/></iconSet>',
            $sheet,
        );
    }

    public function testFiveIconSet(): void
    {
        $sheet = $this->sheetWith(static fn($w) => $w->add(new IconSetNode('B2:B10', IconSet::FiveRating)));
        self::assertStringContainsString(
            '<iconSet iconSet="5Rating"><cfvo type="percent" val="0"/><cfvo type="percent" val="20"/>'
            . '<cfvo type="percent" val="40"/><cfvo type="percent" val="60"/><cfvo type="percent" val="80"/></iconSet>',
            $sheet,
        );
    }

    public function testFourIconSet(): void
    {
        $sheet = $this->sheetWith(static fn($w) => $w->add(new IconSetNode('B2:B10', IconSet::FourRating)));
        self::assertStringContainsString(
            '<iconSet iconSet="4Rating"><cfvo type="percent" val="0"/><cfvo type="percent" val="25"/>'
            . '<cfvo type="percent" val="50"/><cfvo type="percent" val="75"/></iconSet>',
            $sheet,
        );
    }

    public function testEveryIconSetTokenProducesAValidRule(): void
    {
        foreach (IconSet::cases() as $set) {
            $sheet = $this->sheetWith(static fn($w) => $w->add(new IconSetNode('A1:A5', $set)));
            self::assertStringContainsString('iconSet="' . $set->value . '"', $sheet);
            self::assertInstanceOf(\SimpleXMLElement::class, simplexml_load_string($sheet), $set->value);
        }
    }

    public function testMultipleRulesGetDistinctPriorities(): void
    {
        $sheet = $this->sheetWith(static function ($w): void {
            $w->add(new DataBarNode('A1:A5', Color::hex('#638EC6')));
            $w->add(new IconSetNode('B1:B5', IconSet::ThreeArrows));
        });
        self::assertStringContainsString('type="dataBar" priority="1"', $sheet);
        self::assertStringContainsString('type="iconSet" priority="2"', $sheet);
        self::assertInstanceOf(\SimpleXMLElement::class, simplexml_load_string($sheet));
    }

    public function testCellIsBetween(): void
    {
        $style = (new Style())->withBackgroundColor(Color::hex('#FFFF00'));
        $sheet = $this->sheetWith(static fn($w) => $w->add(
            new ConditionalFormatNode('A1:A10', CfOperator::Between, '10', $style, '20'),
        ));
        self::assertStringContainsString(
            'operator="between"><formula>10</formula><formula>20</formula></cfRule>',
            $sheet,
        );
    }

    public function testExpressionRule(): void
    {
        $style = (new Style())->withBold();
        $sheet = $this->sheetWith(static fn($w) => $w->add(new ExpressionFormatNode('A1:A10', '$B1>100', $style)));
        self::assertStringContainsString('<cfRule type="expression" dxfId="0" priority="1">', $sheet);
        self::assertStringContainsString('<formula>$B1&gt;100</formula>', $sheet);
    }

    public function testDuplicateAndUniqueValues(): void
    {
        $style = (new Style())->withBold();
        $dup = $this->sheetWith(static fn($w) => $w->add(new DuplicateValuesNode('A1:A10', $style)));
        self::assertStringContainsString('<cfRule type="duplicateValues" dxfId="0" priority="1"/>', $dup);

        $unique = $this->sheetWith(static fn($w) => $w->add(new DuplicateValuesNode('A1:A10', $style, true)));
        self::assertStringContainsString('<cfRule type="uniqueValues" dxfId="0" priority="1"/>', $unique);
    }

    /**
     * @param callable(\PHPdot\Sheets\Engine\Model\WriterInterface): void $add
     */
    private function sheetWith(callable $add): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'sheets_cfv_');
        $this->tempFiles[] = $path;

        $writer = Spreadsheet::writer($path)->use(new ValidationPlugin());
        $writer->startSheet('Data');
        $writer->addRow(['x', 1]);
        $add($writer);
        $writer->close();

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            self::fail('Cannot open archive.');
        }
        $data = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if ($data === false) {
            self::fail('Worksheet not found.');
        }

        return $data;
    }
}
