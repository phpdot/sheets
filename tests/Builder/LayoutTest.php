<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Tests\Builder;

use PHPdot\Sheets\Engine\Support\RuntimeException;
use PHPdot\Sheets\Engine\Support\WriteException;
use PHPdot\Sheets\Engine\Xlsx\Reader;
use PHPdot\Sheets\Sheets;
use PHPUnit\Framework\TestCase;

/**
 * Phase 3 (layout): sheet layout is buffered and applied before the first row,
 * letter-keyed widths translate correctly, and a layout call after the first row
 * throws (the streaming lifecycle is loud).
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class LayoutTest extends TestCase
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

    public function testLetterWidthsTranslateAndRowsRoundTrip(): void
    {
        $path = $this->tempFile();

        $book = (new Sheets())->write($path);
        $sheet = $book->addSheet('Report');
        $sheet->widths(['A' => 30, 'C' => 14])
            ->freezeRows(1)
            ->freezeColumns(1)
            ->withoutGridlines()
            ->tabColor('FF5500')
            ->hideColumns('B')
            ->landscape()
            ->printArea('A1:C2');
        $sheet->addRow(['Name', 'Note', 'Amount']);
        $sheet->addRow(['Alice', 'x', 100]);
        $book->save();

        $reader = new Reader($path);
        $widths = $reader->columnWidths(0);
        $rows = [];
        foreach ($reader->values(0) as $cells) {
            $rows[] = $cells;
        }
        $reader->close();

        // 'A' -> 0-based 0 -> reader 1-based 1; 'C' -> 2 -> 3.
        self::assertEquals([1 => 30.0, 3 => 14.0], $widths);
        self::assertEquals([['Name', 'Note', 'Amount'], ['Alice', 'x', 100]], $rows);
    }

    public function testLayoutAfterFirstRowThrows(): void
    {
        $book = (new Sheets())->write($this->tempFile());
        $sheet = $book->addSheet('Sheet1');
        $sheet->addRow(['a']);

        $this->expectException(RuntimeException::class);
        $sheet->freezeRows(1);
    }

    public function testAutoSizeWidensForLongerContent(): void
    {
        $path = $this->tempFile();

        $book = (new Sheets())->write($path);
        $book->addSheet('S')->autoSize()->addRow(['x', 'a-much-longer-value']);
        $book->save();

        $reader = new Reader($path);
        $widths = $reader->columnWidths(0);
        $reader->close();

        $first = $widths[1] ?? 0.0;
        $second = $widths[2] ?? 0.0;
        self::assertGreaterThan(0.0, $first);
        self::assertGreaterThan($first, $second);
    }

    public function testDuplicateSheetNameFailsAtAddSheet(): void
    {
        $book = (new Sheets())->write($this->tempFile());
        $book->addSheet('Data');

        $this->expectException(WriteException::class);
        $book->addSheet('DATA');
    }

    private function tempFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'sheets_layout_');
        if ($path === false) {
            self::fail('Cannot create a temp file.');
        }
        $this->paths[] = $path;

        return $path;
    }
}
