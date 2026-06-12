<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Tests\Builder;

use PHPdot\Sheets\Engine\Xlsx\Reader;
use PHPdot\Sheets\Sheets;
use PHPUnit\Framework\TestCase;

/**
 * Phase 1 (write spine): the façade writes a streaming workbook that the engine
 * reader reads back — values, types, and a resolved row style all survive.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class WriteSpineTest extends TestCase
{
    private string $path = '';

    protected function tearDown(): void
    {
        if ($this->path !== '' && is_file($this->path)) {
            @unlink($this->path);
        }
    }

    public function testFacadeWritesAWorkbookThatReadsBack(): void
    {
        $this->path = $this->tempFile();

        $sheets = new Sheets();
        $book = $sheets->write($this->path);
        $book->title('Q2 Sales')->creator('phpdot')->category('Reports');
        $header = $book->style()->bold()->fontColor('FFFFFF')->background('1F4E78');

        $sheet = $book->addSheet('Sales');
        $sheet->addRow(['Product', 'Units', 'Revenue'])->style($header);
        $sheet->addRow(['Widget', 1200, 8400.5]);
        $sheet->addRow(['Gadget', 950, 7125.25])->height(18.0);
        $book->save();

        $reader = new Reader($this->path);

        $values = [];
        $headerStyleId = null;
        foreach ($reader->rows(0) as $rowNum => $cells) {
            $row = [];
            foreach ($cells as $cell) {
                $row[] = $cell->value;
            }
            $values[] = $row;
            if ($rowNum === 1) {
                $headerStyleId = ($cells[0] ?? null)?->styleId;
            }
        }

        self::assertNotNull($headerStyleId, 'Header row should carry a style id.');
        $style = $reader->style($headerStyleId);
        $reader->close();

        self::assertEquals([
            ['Product', 'Units', 'Revenue'],
            ['Widget', 1200, 8400.5],
            ['Gadget', 950, 7125.25],
        ], $values);

        self::assertNotNull($style);
        self::assertTrue($style->bold, 'Header style should round-trip as bold.');
    }

    private function tempFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'sheets_spine_');
        if ($path === false) {
            self::fail('Cannot create a temp file.');
        }

        return $path;
    }
}
