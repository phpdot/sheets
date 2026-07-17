<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Tests\Builder;

use DateTimeImmutable;
use PHPdot\Sheets\Engine\Model\CellType;
use PHPdot\Sheets\Engine\Xlsx\Reader;
use PHPdot\Sheets\Sheets;
use PHPUnit\Framework\TestCase;

/**
 * Phase 5 (datasets): `fill()` and `iterate()->columns()/cast()/map()->write()`
 * turn associative rows into a sheet, streaming any iterable.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class DatasetTest extends TestCase
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

    public function testFillDerivesHeadersFromTheFirstRow(): void
    {
        $users = [
            ['id' => 1, 'name' => 'Omar', 'active' => true],
            ['id' => 2, 'name' => 'Layla', 'active' => false],
        ];

        $path = $this->tempFile();
        $book = (new Sheets())->write($path);
        $book->addSheet('Users')->fill($users);
        $book->save();

        self::assertEquals([
            ['id', 'name', 'active'],
            [1, 'Omar', true],
            [2, 'Layla', false],
        ], $this->readValues($path));
    }

    public function testIterateMapsColumnsAndCastsFields(): void
    {
        $users = [
            ['id' => 1, 'name' => 'Omar',  'is_active' => true],
            ['id' => 2, 'name' => 'Layla', 'is_active' => false],
        ];

        $path = $this->tempFile();
        $book = (new Sheets())->write($path);
        $sheet = $book->addSheet('Users');
        $sheet->iterate($users)
            ->columns(['id' => 'ID', 'name' => 'Full Name', 'is_active' => 'Active'])
            ->cast('is_active', static fn(bool $v): string => $v ? 'Yes' : 'No')
            ->headerStyle($book->style()->bold())
            ->write();
        $book->save();

        self::assertEquals([
            ['ID', 'Full Name', 'Active'],
            [1, 'Omar', 'Yes'],
            [2, 'Layla', 'No'],
        ], $this->readValues($path));
    }

    public function testStreamsAGeneratorAndMapSkipsRows(): void
    {
        $generator = (static function (): \Generator {
            yield ['n' => 1];
            yield ['n' => 2];
            yield ['n' => 3];
        })();

        $path = $this->tempFile();
        $book = (new Sheets())->write($path);
        $book->addSheet('S')->iterate($generator)
            ->map(static fn(array $row): ?array => $row['n'] === 2 ? null : $row)
            ->write();
        $book->save();

        self::assertEquals([['n'], [1], [3]], $this->readValues($path));
    }

    public function testCastToDateTimeProducesADateCell(): void
    {
        $rows = [['created' => '2024-01-15']];

        $path = $this->tempFile();
        $book = (new Sheets())->write($path);
        $book->addSheet('S')->iterate($rows)
            ->cast('created', static fn(string $v): DateTimeImmutable => new DateTimeImmutable($v))
            ->write();
        $book->save();

        $reader = new Reader($path);
        $type = null;
        foreach ($reader->rows(0) as $rowNum => $cells) {
            if ($rowNum === 2) {
                $type = ($cells[0] ?? null)?->type;

                break;
            }
        }
        $reader->close();

        self::assertSame(CellType::Date, $type);
    }

    /**
     * @return list<list<int|float|string|bool|null>>
     */
    private function readValues(string $path): array
    {
        $reader = new Reader($path);
        $rows = [];
        foreach ($reader->values(0) as $cells) {
            $rows[] = $cells;
        }
        $reader->close();

        return $rows;
    }

    private function tempFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'sheets_dataset_');
        if ($path === false) {
            self::fail('Cannot create a temp file.');
        }
        $this->paths[] = $path;

        return $path;
    }
}
