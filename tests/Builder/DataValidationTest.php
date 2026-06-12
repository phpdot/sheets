<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Tests\Builder;

use DateTimeImmutable;
use PHPdot\Sheets\Engine\Support\InvalidArgumentException;
use PHPdot\Sheets\Engine\Support\RuntimeException;
use PHPdot\Sheets\Engine\Xlsx\Reader;
use PHPdot\Sheets\Sheets;
use PHPUnit\Framework\TestCase;

/**
 * Phase 4d (data validation): the `Rule` builder off `$sheet` covers numeric/date/
 * custom rules, dropdowns (inline + range), prompts/errors, and `required`. A
 * comma in an inline list and a typeless rule both fail loudly.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class DataValidationTest extends TestCase
{
    /** @var list<string> */
    private array $paths = [];

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    public function testValidationsSerializeAndTheFileStaysValid(): void
    {
        $path = $this->tempFile();

        $book = (new Sheets())->write($path);
        $sheet = $book->addSheet('Form');
        $sheet->addRow(['Qty', 'Date', 'Choice', 'List', 'Ref']);
        $sheet->validate('A2:A100')->wholeNumber()->between(1, 100)
            ->prompt('Quantity', 'Enter 1-100')->error('Invalid', 'Out of range');
        $sheet->validate('B2:B100')->date()->onOrAfter(new DateTimeImmutable('2024-01-01'));
        $sheet->validate('C2:C100')->custom('ISNUMBER(C2)');
        $sheet->dropdown('D2:D100', ['Yes', 'No'])->required();
        $sheet->dropdownFrom('E2:E100', 'Form!$A$1:$A$3');
        $book->save();

        $reader = new Reader($path);
        $rows = [];
        foreach ($reader->values(0) as $cells) {
            $rows[] = $cells;
        }
        $reader->close();
        self::assertEquals([['Qty', 'Date', 'Choice', 'List', 'Ref']], $rows);

        self::assertStringContainsString('dataValidation', $this->sheetXml($path));
    }

    public function testInlineListWithCommaThrowsAtSave(): void
    {
        $book = (new Sheets())->write($this->tempFile());
        $sheet = $book->addSheet('S');
        $sheet->addRow(['x']);
        $sheet->dropdown('A1:A1', ['a,b']);

        $this->expectException(InvalidArgumentException::class);
        $book->save();
    }

    public function testValidationWithoutTypeThrowsAtSave(): void
    {
        $book = (new Sheets())->write($this->tempFile());
        $sheet = $book->addSheet('S');
        $sheet->addRow(['x']);
        $sheet->validate('A1:A1');

        $this->expectException(RuntimeException::class);
        $book->save();
    }

    private function sheetXml(string $path): string
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return '';
        }
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        return $xml === false ? '' : $xml;
    }

    private function tempFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'sheets_dv_');
        if ($path === false) {
            self::fail('Cannot create a temp file.');
        }
        $this->paths[] = $path;

        return $path;
    }
}
