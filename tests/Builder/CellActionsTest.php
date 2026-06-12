<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Tests\Builder;

use PHPdot\Sheets\Sheets;
use PHPUnit\Framework\TestCase;

/**
 * Merge, hyperlink, comment and auto-filter (trailers — they don't lock layout)
 * write through the façade and read back through the read façade.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class CellActionsTest extends TestCase
{
    private string $path = '';

    protected function tearDown(): void
    {
        if ($this->path !== '' && is_file($this->path)) {
            @unlink($this->path);
        }
    }

    public function testMergeLinkCommentAndFilterRoundTrip(): void
    {
        $this->path = $this->tempFile();

        $sheets = new Sheets();
        $book = $sheets->write($this->path);
        $sheet = $book->addSheet('S');
        $sheet->addRow(['A', 'B', 'C']);
        $sheet->addRow([1, 2, 3]);
        $sheet->merge('A1:C1');
        $sheet->link('A2', 'https://example.com', tooltip: 'Visit');
        $sheet->comment('B2', 'Check this', author: 'Omar');
        $sheet->filter('A1:C1');
        $book->save();

        $in = $sheets->read($this->path);
        $read = $in->sheet('S');

        self::assertContains('A1:C1', $read->mergedCells());
        self::assertSame(['A2' => 'https://example.com'], $read->links());
        self::assertSame(['B2' => 'Check this'], $read->comments());

        $in->close();

        self::assertStringContainsString('autoFilter', $this->sheetXml());
    }

    private function sheetXml(): string
    {
        $zip = new \ZipArchive();
        if ($zip->open($this->path) !== true) {
            return '';
        }
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        return $xml === false ? '' : $xml;
    }

    private function tempFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'sheets_actions_');
        if ($path === false) {
            self::fail('Cannot create a temp file.');
        }

        return $path;
    }
}
