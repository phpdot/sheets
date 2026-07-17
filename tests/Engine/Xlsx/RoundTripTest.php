<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Tests\Engine\Xlsx;

use PHPdot\Sheets\Engine\Model\Cell;
use PHPdot\Sheets\Engine\Model\CellType;
use PHPdot\Sheets\Engine\Model\NumberFormats;
use PHPdot\Sheets\Engine\Model\Row;
use PHPdot\Sheets\Engine\Model\SheetInfo;
use PHPdot\Sheets\Engine\Model\Style;
use PHPdot\Sheets\Spreadsheet;
use PHPUnit\Framework\TestCase;

final class RoundTripTest extends TestCase
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

    public function testScalarValuesRoundTripExactly(): void
    {
        $input = [
            ['Name', 'Score', 'Active', 'Note'],
            ['Alice', 9.5, true, null],
            ['Bob', 42, false, 'a & <b>'],
        ];

        self::assertSame($input, $this->roundTripValues($input));
    }

    public function testCellTypesAreRecovered(): void
    {
        $path = $this->newPath();
        $writer = Spreadsheet::writer($path);
        $writer->startSheet('Data');
        $writer->addRowObject(new Row([
            new Cell('SUM(A1:A2)', CellType::Formula),
            new Cell(44927, CellType::Date),
            new Cell('x', CellType::String),
        ]));
        $writer->close();

        $reader = Spreadsheet::reader($path);
        $collected = [];
        foreach ($reader->rows() as $cells) {
            $collected[] = $cells;
        }
        $reader->close();

        $tuples = array_map(
            static fn(Cell $cell): array => [$cell->type, $cell->value],
            $collected[0] ?? [],
        );

        self::assertSame([
            [CellType::Formula, 'SUM(A1:A2)'], // our writer emits no cached value → formula text returned
            [CellType::Number, 44927],         // Date with NO date numFmt carries no signal → plain number
            [CellType::String, 'x'],
        ], $tuples);
    }

    public function testDateFormattedCellsReadBackTypedAsDates(): void
    {
        $path = $this->newPath();
        $writer = Spreadsheet::writer($path);
        $dateStyle = $writer->registerStyle((new Style())->withNumberFormat(NumberFormats::DATE));
        $moneyStyle = $writer->registerStyle((new Style())->withNumberFormat(NumberFormats::CURRENCY_USD));
        $writer->startSheet('Data');
        $writer->addRowObject(new Row([
            new Cell(45000.5, CellType::Date, $dateStyle),
            new Cell(45000.5, CellType::Number, $moneyStyle), // currency code has no date tokens
            new Cell(45000.5, CellType::Number),
        ]));
        $writer->close();

        $reader = Spreadsheet::reader($path);
        $collected = [];
        foreach ($reader->rows() as $cells) {
            $collected[] = $cells;
        }
        $reader->close();

        $tuples = array_map(
            static fn(Cell $cell): array => [$cell->type, $cell->value],
            $collected[0] ?? [],
        );

        self::assertSame([
            [CellType::Date, 45000.5],   // date numFmt → typed Date, value stays the serial
            [CellType::Number, 45000.5],
            [CellType::Number, 45000.5],
        ], $tuples);
    }

    public function testMultipleSheetsAreResolvedAndReadable(): void
    {
        $path = $this->newPath();
        $writer = Spreadsheet::writer($path);
        $writer->startSheet('Report');
        $writer->addRow(['a', 1]);
        $writer->startSheet('Summary');
        $writer->addRow(['b', 2]);
        $writer->close();

        $reader = Spreadsheet::reader($path);

        $names = array_map(static fn(SheetInfo $s): string => $s->name, $reader->sheets());
        self::assertSame(['Report', 'Summary'], $names);

        self::assertSame([['a', 1]], $this->collectValues($reader->values(0)));
        self::assertSame([['b', 2]], $this->collectValues($reader->values(1)));

        $reader->close();
    }

    public function testStyleIndexRoundTrips(): void
    {
        $path = $this->newPath();
        $writer = Spreadsheet::writer($path);
        $styleId = $writer->registerStyle((new Style())->withBold());
        $writer->startSheet('Data');
        $writer->addRow(['Header'], $styleId);
        $writer->close();

        $reader = Spreadsheet::reader($path);
        $collected = [];
        foreach ($reader->rows() as $cells) {
            $collected[] = $cells;
        }
        $reader->close();

        $styleIds = array_map(static fn(Cell $cell): ?int => $cell->styleId, $collected[0] ?? []);
        self::assertSame([$styleId], $styleIds);
    }

    public function testReaderMemoryStaysBoundedRegardlessOfRowCount(): void
    {
        $path = $this->newPath();
        $writer = Spreadsheet::writer($path);
        $writer->startSheet('Bench');
        for ($i = 1; $i <= 50000; $i++) {
            $writer->addRow(["User {$i}", $i, $i * 1.5]);
        }
        $writer->close();

        gc_collect_cycles();
        $before = memory_get_usage();

        $reader = Spreadsheet::reader($path);
        $rowCount = 0;
        foreach ($reader->values() as $ignored) {
            $rowCount++;
        }
        $reader->close();

        $growth = memory_get_usage() - $before;

        self::assertSame(50000, $rowCount);
        self::assertLessThan(
            4 * 1024 * 1024,
            $growth,
            sprintf('Reader retained %d bytes streaming 50k rows — memory is not bounded.', $growth),
        );
    }

    /**
     * @param list<list<int|float|string|bool|null>> $rows
     *
     * @return list<list<int|float|string|bool|null>>
     */
    private function roundTripValues(array $rows): array
    {
        $path = $this->newPath();
        $writer = Spreadsheet::writer($path);
        $writer->startSheet('Sheet');
        foreach ($rows as $row) {
            $writer->addRow($row);
        }
        $writer->close();

        $reader = Spreadsheet::reader($path);
        $out = $this->collectValues($reader->values());
        $reader->close();

        return $out;
    }

    /**
     * @param iterable<int, list<int|float|string|bool|null>> $rows
     *
     * @return list<list<int|float|string|bool|null>>
     */
    private function collectValues(iterable $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[] = $row;
        }

        return $out;
    }

    private function newPath(): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'sheets_rt_');
        $this->tempFiles[] = $path;

        return $path;
    }
}
