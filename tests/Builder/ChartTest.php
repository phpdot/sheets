<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Tests\Builder;

use PHPdot\Sheets\Engine\Support\InvalidArgumentException;
use PHPdot\Sheets\Engine\Xlsx\Reader;
use PHPdot\Sheets\Sheets;
use PHPUnit\Framework\TestCase;

/**
 * Phase 4b (charts): `addChart('bar')` builds a chart fluently and embeds a chart
 * part; an unknown type throws at `addChart`; an incompatible combo series is
 * rejected by the engine's validation when the sheet flushes.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class ChartTest extends TestCase
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

    public function testChartEmbedsAndTheFileStaysValid(): void
    {
        $path = $this->tempFile();

        $book = (new Sheets())->write($path);
        $sheet = $book->addSheet('Sales');
        $sheet->addRow(['Product', 'Revenue']);
        $sheet->addRow(['Widget', 100]);
        $sheet->addRow(['Gadget', 80]);
        $sheet->addChart('bar')
            ->title('Revenue by Product')
            ->series('Sales!$B$2:$B$3', name: 'Revenue', color: 'CC0000')
            ->labels('Sales!$A$2:$A$3')
            ->legend('bottom')
            ->dataLabels(value: true, position: 'outsideEnd')
            ->at('D2', [480, 288]);
        $book->save();

        self::assertTrue($this->zipHasMember($path, 'xl/charts/'), 'Chart part should be present.');

        $reader = new Reader($path);
        $rows = [];
        foreach ($reader->values(0) as $cells) {
            $rows[] = $cells;
        }
        $reader->close();
        self::assertEquals([['Product', 'Revenue'], ['Widget', 100], ['Gadget', 80]], $rows);
    }

    public function testUnknownChartTypeThrows(): void
    {
        $book = (new Sheets())->write($this->tempFile());
        $sheet = $book->addSheet('S');

        $this->expectException(InvalidArgumentException::class);
        $sheet->addChart('pie3d');
    }

    public function testIncompatibleComboSeriesThrowsAtSave(): void
    {
        $path = $this->tempFile();

        $book = (new Sheets())->write($path);
        $sheet = $book->addSheet('S');
        $sheet->addRow(['a', 'b']);
        $sheet->addChart('bar')
            ->series('S!$A$1:$A$1')
            ->series('S!$B$1:$B$1', as: 'pie')
            ->at('D1', [300, 200]);

        $this->expectException(InvalidArgumentException::class);
        $book->save();
    }

    private function zipHasMember(string $path, string $prefix): bool
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return false;
        }

        $found = false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            if (str_starts_with((string) $zip->getNameIndex($i), $prefix)) {
                $found = true;

                break;
            }
        }
        $zip->close();

        return $found;
    }

    private function tempFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'sheets_chart_');
        if ($path === false) {
            self::fail('Cannot create a temp file.');
        }
        $this->paths[] = $path;

        return $path;
    }
}
