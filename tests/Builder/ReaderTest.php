<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Tests\Builder;

use DateTimeImmutable;
use PHPdot\Sheets\Engine\Support\ExcelDate;
use PHPdot\Sheets\Sheets;
use PHPUnit\Framework\TestCase;

/**
 * Phase 6 (reader façade): `read()` → `sheet()` → `values()/rows()/records()`,
 * cell predicates, letter-keyed widths, and a configurable read dataset — all via
 * the façade, round-tripping a workbook written by the façade.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class ReaderTest extends TestCase
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

    public function testValuesRecordsAndCellPredicatesRoundTrip(): void
    {
        $path = $this->tempFile();
        $serial = (int) ExcelDate::toSerial(new DateTimeImmutable('2024-01-15'));

        $book = (new Sheets())->write($path);
        $sheet = $book->addSheet('Users');
        $sheet->widths(['A' => 30]);
        $sheet->addRow(['id', 'name', 'joined']);
        $data = $sheet->addRow();
        $data->addCell(1);
        $data->addCell('Omar');
        $data->addCell(new DateTimeImmutable('2024-01-15'))->asDate();
        $book->save();

        $xlsx = (new Sheets())->read($path);

        self::assertSame(['Users'], $xlsx->sheetNames());

        $read = $xlsx->sheet('Users');
        self::assertEquals(['A' => 30.0], $read->widths());
        self::assertEquals(
            [1 => ['id', 'name', 'joined'], 2 => [1, 'Omar', $serial]],
            iterator_to_array($read->values()),
        );
        self::assertEquals(
            [['id' => 1, 'name' => 'Omar', 'joined' => $serial]],
            iterator_to_array($read->records()),
        );

        $dateCell = null;
        foreach ($read->rows() as $rowNum => $cells) {
            if ($rowNum === 2) {
                $dateCell = $cells[2] ?? null;

                break;
            }
        }
        if ($dateCell === null) {
            self::fail('Expected a date cell at C2.');
        }

        self::assertTrue($dateCell->isDate());
        self::assertSame('date', $dateCell->type());
        self::assertInstanceOf(DateTimeImmutable::class, $dateCell->toDateTime());

        $xlsx->close();
    }

    public function testReadIterateRenamesColumnsAndCasts(): void
    {
        $path = $this->tempFile();

        $book = (new Sheets())->write($path);
        $sheet = $book->addSheet('Users');
        $sheet->addRow(['ID', 'Active']);
        $sheet->addRow([1, 'Yes']);
        $sheet->addRow([2, 'No']);
        $book->save();

        $xlsx = (new Sheets())->read($path);
        $records = iterator_to_array(
            $xlsx->sheet('Users')->iterate()
                ->columns(['ID' => 'id', 'Active' => 'is_active'])
                ->cast('is_active', static fn($v): bool => $v === 'Yes')
                ->records(),
        );
        $xlsx->close();

        self::assertEquals([
            ['id' => 1, 'is_active' => true],
            ['id' => 2, 'is_active' => false],
        ], $records);
    }

    private function tempFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'sheets_reader_');
        if ($path === false) {
            self::fail('Cannot create a temp file.');
        }
        $this->paths[] = $path;

        return $path;
    }
}
