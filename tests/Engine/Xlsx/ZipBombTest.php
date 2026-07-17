<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Tests\Engine\Xlsx;

use PHPdot\Sheets\Engine\Model\ReadOptions;
use PHPdot\Sheets\Engine\Support\ReadException;
use PHPdot\Sheets\Engine\Xlsx\Reader;
use PHPdot\Sheets\Sheets;
use PHPUnit\Framework\TestCase;

/**
 * Decompression-bomb protection: a hostile archive is refused, every limit is
 * enforced and configurable, and — the part that matters for a streaming
 * library — a legitimately large file is never mistaken for an attack.
 */
final class ZipBombTest extends TestCase
{
    /** @var list<string> */
    private array $tmp = [];

    protected function tearDown(): void
    {
        foreach ($this->tmp as $file) {
            @unlink($file);
        }
        $this->tmp = [];
    }

    public function testHighCompressionRatioPartIsRejected(): void
    {
        $path = $this->validWorkbook();
        // 2 MB of one repeated byte compresses to a few KB — a ratio far above
        // any real XML, and over the 1 MB floor, so the gate fires.
        $this->injectEntry($path, 'xl/bomb.xml', str_repeat(' ', 2_000_000));

        $this->expectException(ReadException::class);
        $this->expectExceptionMessageMatches('/zip bomb/i');
        new Reader($path);
    }

    public function testWholeReadByteCapIsEnforced(): void
    {
        $path = $this->validWorkbook();

        // Ratio gate off so we isolate the whole-read cap; workbook.xml alone
        // exceeds 100 bytes, so the very first whole read trips it.
        $this->expectException(ReadException::class);
        $this->expectExceptionMessageMatches('/whole-read limit/');
        new Reader($path, new ReadOptions(maxCompressionRatio: 0, maxWholeReadBytes: 100));
    }

    public function testSharedStringBudgetIsEnforced(): void
    {
        $path = $this->workbookWithSharedStrings();

        $this->expectException(ReadException::class);
        $this->expectExceptionMessageMatches('/Shared-string table/');
        new Reader($path, new ReadOptions(maxSharedStringBytes: 50));
    }

    public function testProtectionCanBeDisabledForTrustedFiles(): void
    {
        $path = $this->validWorkbook();
        $this->injectEntry($path, 'xl/bomb.xml', str_repeat(' ', 2_000_000));

        // Every limit at 0 — the (never-read) bomb entry no longer blocks load.
        $reader = new Reader($path, new ReadOptions(
            maxCompressionRatio: 0,
            maxWholeReadBytes: 0,
            maxSharedStringBytes: 0,
        ));

        self::assertCount(1, $reader->sheets());
    }

    public function testLegitimateLargeFileIsNotRejected(): void
    {
        $path = $this->tmpPath();
        $xlsx = (new Sheets())->write($path);
        $sheet = $xlsx->addSheet('Big');
        for ($i = 1; $i <= 5000; $i++) {
            $sheet->addRow(["User {$i}", "user{$i}@example.com", $i * 3, $i % 2 === 0]);
        }
        $xlsx->save();

        // Default protection ON — a real 5k-row export must read cleanly.
        $rows = iterator_to_array((new Reader($path))->rows());
        self::assertCount(5000, $rows);
    }

    private function tmpPath(): string
    {
        $path = sys_get_temp_dir() . '/zbomb_' . uniqid('', true) . '.xlsx';
        $this->tmp[] = $path;

        return $path;
    }

    private function validWorkbook(): string
    {
        $path = $this->tmpPath();
        $xlsx = (new Sheets())->write($path);
        $sheet = $xlsx->addSheet('Data');
        $sheet->addRow(['Name', 'Score']);
        $sheet->addRow(['Alice', 91]);
        $sheet->addRow(['Bob', 72]);
        $xlsx->save();

        return $path;
    }

    private function workbookWithSharedStrings(): string
    {
        $path = $this->tmpPath();
        $xlsx = (new Sheets())->write($path, sharedStrings: true);
        $sheet = $xlsx->addSheet('Strings');
        for ($i = 0; $i < 50; $i++) {
            $sheet->addRow(['a shared string value number ' . $i]);
        }
        $xlsx->save();

        return $path;
    }

    private function injectEntry(string $path, string $name, string $contents): void
    {
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($path) === true);
        $zip->addFromString($name, $contents);
        $zip->close();
    }
}
