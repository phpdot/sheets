<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Tests\Builder;

use PHPdot\Sheets\Engine\Xlsx\Reader;
use PHPdot\Sheets\Sheets;
use PHPUnit\Framework\TestCase;

/**
 * Phase: dataset column formatting — `iterate()->format([...])` number-formats a
 * data column; unformatted columns stay plain.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class DatasetFormatTest extends TestCase
{
    private string $path = '';

    protected function tearDown(): void
    {
        if ($this->path !== '' && is_file($this->path)) {
            @unlink($this->path);
        }
    }

    public function testFormatAppliesANumberFormatToADataColumn(): void
    {
        $this->path = $this->tempFile();

        $rows = [
            ['name' => 'A', 'amount' => 1234.5],
            ['name' => 'B', 'amount' => 678.0],
        ];

        $book = (new Sheets())->write($this->path);
        $book->addSheet('S')->iterate($rows)
            ->format(['amount' => 'currency'])
            ->write();
        $book->save();

        $reader = new Reader($this->path);
        $amountFormat = null;
        $nameFormat = null;
        foreach ($reader->rows(0) as $rowNum => $cells) {
            if ($rowNum === 2) {
                $amount = $cells[1] ?? null;
                $name = $cells[0] ?? null;
                if ($amount !== null) {
                    $amountFormat = $reader->style($amount->styleId)?->numberFormat;
                }
                if ($name !== null) {
                    $nameFormat = $reader->style($name->styleId)?->numberFormat;
                }

                break;
            }
        }
        $reader->close();

        self::assertNotNull($amountFormat, 'Formatted column should carry a number format.');
        self::assertStringContainsString('$', $amountFormat);
        self::assertNull($nameFormat, 'Unformatted column should have no style.');
    }

    private function tempFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'sheets_dsfmt_');
        if ($path === false) {
            self::fail('Cannot create a temp file.');
        }

        return $path;
    }
}
