<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Tests\Builder;

use PHPdot\Sheets\Engine\Support\InvalidArgumentException;
use PHPdot\Sheets\Engine\Support\RuntimeException;
use PHPdot\Sheets\Engine\Xlsx\Reader;
use PHPdot\Sheets\Sheets;
use PHPUnit\Framework\TestCase;

/**
 * Phase 4c (conditional formatting): the CF builders off `$sheet` serialize into
 * the worksheet; a rule missing its fill throws at save; an unknown icon set
 * throws eagerly.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class ConditionalFormatTest extends TestCase
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

    public function testConditionalFormatsSerializeAndTheFileStaysValid(): void
    {
        $path = $this->tempFile();

        $book = (new Sheets())->write($path);
        $green = $book->style()->background('C6EFCE');

        $sheet = $book->addSheet('Scores');
        $sheet->addRow(['Score']);
        $sheet->addRow([95]);
        $sheet->addRow([40]);
        $sheet->highlight('A2:A3')->greaterThan(50)->fill($green);
        $sheet->highlight('A2:A3')->between(10, 99)->fill($green);
        $sheet->dataBars('A2:A3', '638EC6');
        $sheet->colorScale('A2:A3', from: 'FFFFFF', to: '00B050', mid: 'FFEB84');
        $sheet->iconSet('A2:A3', '3arrows');
        $sheet->duplicates('A2:A3')->fill($green);
        $sheet->expression('A2:A3', '$A2>90')->fill($green);
        $book->save();

        $reader = new Reader($path);
        $rows = [];
        foreach ($reader->values(0) as $cells) {
            $rows[] = $cells;
        }
        $reader->close();
        self::assertEquals([['Score'], [95], [40]], $rows);

        $xml = $this->sheetXml($path);
        self::assertStringContainsString('conditionalFormatting', $xml);
        self::assertStringContainsString('dataBar', $xml);
        self::assertStringContainsString('colorScale', $xml);
        self::assertStringContainsString('iconSet', $xml);
    }

    public function testHighlightWithoutFillThrowsAtSave(): void
    {
        $book = (new Sheets())->write($this->tempFile());
        $sheet = $book->addSheet('S');
        $sheet->addRow([1]);
        $sheet->highlight('A1:A1')->greaterThan(0);

        $this->expectException(RuntimeException::class);
        $book->save();
    }

    public function testUnknownIconSetThrows(): void
    {
        $book = (new Sheets())->write($this->tempFile());
        $sheet = $book->addSheet('S');

        $this->expectException(InvalidArgumentException::class);
        $sheet->iconSet('A1:A1', '7stars');
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
        $path = tempnam(sys_get_temp_dir(), 'sheets_cf_');
        if ($path === false) {
            self::fail('Cannot create a temp file.');
        }
        $this->paths[] = $path;

        return $path;
    }
}
