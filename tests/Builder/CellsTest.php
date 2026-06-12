<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Tests\Builder;

use DateTimeImmutable;
use PHPdot\Sheets\Engine\Model\CellType;
use PHPdot\Sheets\Engine\Support\ExcelDate;
use PHPdot\Sheets\Engine\Xlsx\Reader;
use PHPdot\Sheets\Sheets;
use PHPUnit\Framework\TestCase;

/**
 * Phase 2 (cells): `addCell()` returns a cell whose type the developer chooses
 * with `as*()` (or which is inferred), decorated with `style()`/`format()` —
 * all surviving a round-trip through the engine reader.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class CellsTest extends TestCase
{
    private string $path = '';

    protected function tearDown(): void
    {
        if ($this->path !== '' && is_file($this->path)) {
            @unlink($this->path);
        }
    }

    public function testChosenCellTypesRoundTrip(): void
    {
        $this->path = $this->tempFile();

        $sheets = new Sheets();
        $book = $sheets->write($this->path);
        $sheet = $book->addSheet('Data');
        $row = $sheet->addRow();
        $row->addCell('Total')->asText();
        $row->addCell('=1+2')->asFormula();
        $row->addCell(new DateTimeImmutable('2024-01-15'))->asDate();
        $row->addCell(0.85)->asNumber()->format('percent');
        $row->addCell(true)->asBool();
        $row->addCell('00123')->asText();
        $book->save();

        [$types, $values] = $this->readFirstRow();

        self::assertSame(
            [CellType::String, CellType::Formula, CellType::Date, CellType::Number, CellType::Bool, CellType::String],
            $types,
        );
        self::assertSame(
            ['Total', '1+2', (int) ExcelDate::toSerial(new DateTimeImmutable('2024-01-15')), 0.85, true, '00123'],
            $values,
        );
    }

    public function testTypesAreInferredWhenNotChosen(): void
    {
        $this->path = $this->tempFile();

        $sheets = new Sheets();
        $book = $sheets->write($this->path);
        $sheet = $book->addSheet('Data');
        $row = $sheet->addRow();
        $row->addCell('text');
        $row->addCell(42);
        $row->addCell(3.5);
        $row->addCell(false);
        $row->addCell(new DateTimeImmutable('2020-06-01'));
        $book->save();

        [$types] = $this->readFirstRow();

        self::assertSame(
            [CellType::String, CellType::Number, CellType::Number, CellType::Bool, CellType::Date],
            $types,
        );
    }

    /**
     * @return array{0: list<CellType>, 1: list<int|float|string|bool|null>}
     */
    private function readFirstRow(): array
    {
        $reader = new Reader($this->path);
        $types = [];
        $values = [];
        foreach ($reader->rows(0) as $rowNum => $cells) {
            if ($rowNum !== 1) {
                continue;
            }
            foreach ($cells as $cell) {
                $types[] = $cell->type;
                $values[] = $cell->value;
            }
        }
        $reader->close();

        return [$types, $values];
    }

    private function tempFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'sheets_cells_');
        if ($path === false) {
            self::fail('Cannot create a temp file.');
        }

        return $path;
    }
}
