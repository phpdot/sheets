<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Tests\Builder;

use PHPdot\Sheets\Engine\Xlsx\Reader;
use PHPdot\Sheets\Sheets;
use PHPUnit\Framework\TestCase;

/**
 * The four conveniences: `currentRow()`, the `{row}` formula token, the `header()`
 * sugar, and the `cellRef()`/`colRef()` ref helpers.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class ConveniencesTest extends TestCase
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

    public function testCurrentRowTracksTheAddedRow(): void
    {
        $book = (new Sheets())->write($this->tempFile());
        $sheet = $book->addSheet('S');

        self::assertSame(0, $sheet->currentRow());
        $sheet->addRow(['a']);
        self::assertSame(1, $sheet->currentRow());
        $sheet->addRow(['b']);
        self::assertSame(2, $sheet->currentRow());

        $book->save();
    }

    public function testRowTokenResolvesToTheRowNumber(): void
    {
        $path = $this->tempFile();

        $book = (new Sheets())->write($path);
        $sheet = $book->addSheet('Calc');
        $sheet->addRow(['x', 'y', 'product']);
        $sheet->addRow([2, 3]);
        $sheet->addRow([4, 5])->addCell('=A{row}*B{row}')->asFormula();
        $book->save();

        $reader = new Reader($path);
        $formulas = $reader->formulas(0);
        $reader->close();

        self::assertSame('A3*B3', $formulas['C3'] ?? null);
    }

    public function testHeaderWritesAndStylesTheRow(): void
    {
        $path = $this->tempFile();

        $book = (new Sheets())->write($path);
        $sheet = $book->addSheet('S');
        $sheet->header(['Product', 'Units'], $book->style()->bold());
        $sheet->addRow(['Widget', 5]);
        $book->save();

        $reader = new Reader($path);
        $headerStyleId = null;
        foreach ($reader->rows(0) as $rowNum => $cells) {
            if ($rowNum === 1) {
                $headerStyleId = ($cells[0] ?? null)?->styleId;

                break;
            }
        }
        self::assertNotNull($headerStyleId);
        $style = $reader->style($headerStyleId);
        $reader->close();

        self::assertNotNull($style);
        self::assertTrue($style->bold);
    }

    public function testRefHelpersProduceQualifiedAbsoluteRefs(): void
    {
        $book = (new Sheets())->write($this->tempFile());
        $sheet = $book->addSheet('Sales');

        self::assertSame('Sales!$D$1', $sheet->cellRef('D1'));
        self::assertSame('Sales!$D$2:$D$6', $sheet->colRef('D', 2, 6));

        $spaced = $book->addSheet('My Data');
        self::assertSame("'My Data'!\$A\$1", $spaced->cellRef('A1'));

        $book->save();
    }

    private function tempFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'sheets_conv_');
        if ($path === false) {
            self::fail('Cannot create a temp file.');
        }
        $this->paths[] = $path;

        return $path;
    }
}
