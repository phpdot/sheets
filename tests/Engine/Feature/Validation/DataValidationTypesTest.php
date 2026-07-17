<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Tests\Engine\Feature\Validation;

use PHPdot\Sheets\Engine\Feature\Validation\DataValidationNode;
use PHPdot\Sheets\Engine\Feature\Validation\ValidationOperator;
use PHPdot\Sheets\Engine\Feature\Validation\ValidationPlugin;
use PHPdot\Sheets\Engine\Support\InvalidArgumentException;
use PHPdot\Sheets\Spreadsheet;
use PHPUnit\Framework\TestCase;

final class DataValidationTypesTest extends TestCase
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

    public function testWholeNumberBetween(): void
    {
        $sheet = $this->sheetWith(static fn($w) => $w->add(
            DataValidationNode::wholeNumber('A1:A10', ValidationOperator::Between, '1', '100'),
        ));
        self::assertStringContainsString('type="whole" operator="between"', $sheet);
        self::assertStringContainsString('<formula1>1</formula1><formula2>100</formula2>', $sheet);
    }

    public function testDecimalGreaterThan(): void
    {
        $sheet = $this->sheetWith(static fn($w) => $w->add(
            DataValidationNode::decimal('A1:A10', ValidationOperator::GreaterThan, '0.5'),
        ));
        self::assertStringContainsString('type="decimal" operator="greaterThan"', $sheet);
        self::assertStringContainsString('<formula1>0.5</formula1>', $sheet);
    }

    public function testTextLength(): void
    {
        $sheet = $this->sheetWith(static fn($w) => $w->add(
            DataValidationNode::textLength('A1:A10', ValidationOperator::LessThanOrEqual, '10'),
        ));
        self::assertStringContainsString('type="textLength" operator="lessThanOrEqual"', $sheet);
    }

    public function testCustomFormulaHasNoOperator(): void
    {
        $sheet = $this->sheetWith(static fn($w) => $w->add(
            DataValidationNode::custom('A1:A10', 'ISNUMBER(A1)'),
        ));
        self::assertStringContainsString('type="custom"', $sheet);
        self::assertStringNotContainsString('operator=', $sheet);
        self::assertStringContainsString('<formula1>ISNUMBER(A1)</formula1>', $sheet);
    }

    public function testListFromRangeUsesRangeNotQuotedValues(): void
    {
        $sheet = $this->sheetWith(static fn($w) => $w->add(
            DataValidationNode::listFromRange('A1:A10', 'Lists!$D$1:$D$5'),
        ));
        self::assertStringContainsString('type="list"', $sheet);
        self::assertStringContainsString('<formula1>Lists!$D$1:$D$5</formula1>', $sheet);
        self::assertStringNotContainsString('<formula1>"', $sheet);
    }

    public function testInputAndErrorMessages(): void
    {
        $sheet = $this->sheetWith(static fn($w) => $w->add(
            DataValidationNode::list('A1:A10', ['Yes', 'No'])
                ->withInputMessage('Pick', 'Choose Yes or No')
                ->withErrorMessage('Invalid', 'Must be Yes or No'),
        ));
        self::assertStringContainsString('showInputMessage="1" promptTitle="Pick" prompt="Choose Yes or No"', $sheet);
        self::assertStringContainsString('showErrorMessage="1" errorTitle="Invalid" error="Must be Yes or No"', $sheet);
    }

    public function testInlineListValueWithCommaIsRejected(): void
    {
        // Excel's inline list uses the comma as its separator with no escape —
        // a value containing one would silently split into two items.
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('listFromRange');
        DataValidationNode::list('A1:A10', ['ok', 'broken,value']);
    }

    public function testInlineListQuotesAreDoubled(): void
    {
        $sheet = $this->sheetWith(static fn($w) => $w->add(
            DataValidationNode::list('A1:A10', ['plain', 'has"quote']),
        ));
        self::assertStringContainsString('<formula1>"plain,has""quote"</formula1>', $sheet);
    }

    /**
     * @param callable(\PHPdot\Sheets\Engine\Model\WriterInterface): void $add
     */
    private function sheetWith(callable $add): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'sheets_dv_');
        $this->tempFiles[] = $path;

        $writer = Spreadsheet::writer($path)->use(new ValidationPlugin());
        $writer->startSheet('Data');
        $writer->addRow(['x']);
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
