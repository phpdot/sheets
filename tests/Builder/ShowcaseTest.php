<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Tests\Builder;

use DateTimeImmutable;
use PHPdot\Sheets\Engine\Support\ExcelDate;
use PHPdot\Sheets\Sheets;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end: a fully-featured workbook written entirely through the façade —
 * properties, styles, layout, fast + rich rows, conditional formatting, data
 * validation, a chart, an image, and a dataset — then read back through the
 * façade. Proves the whole redesigned surface works together over one import.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class ShowcaseTest extends TestCase
{
    private string $path = '';

    protected function tearDown(): void
    {
        if ($this->path !== '' && is_file($this->path)) {
            @unlink($this->path);
        }
    }

    public function testEverythingWorksTogether(): void
    {
        $this->path = $this->tempFile();
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGP4z8DwHwAFBQIAX8jx0gAAAABJRU5ErkJggg==',
            true,
        );
        self::assertNotFalse($png);

        $sheets = new Sheets();
        $book = $sheets->write($this->path);
        $book->title('Q2 Report')->creator('phpdot')->category('Reports');

        $header = $book->style()->bold()->fontColor('FFFFFF')->background('1F4E78')->align('center');
        $money = $book->style()->numberFormat('currency');

        // --- a styled, laid-out report with every feature ---
        $sales = $book->addSheet('Sales');
        $sales->widths(['A' => 24, 'B' => 12, 'C' => 14])->freezeRows(1)->tabColor('1F4E78');
        $sales->addRow(['Product', 'Units', 'Revenue'])->style($header);
        $sales->addRow(['Widget', 1200, 8400.50]);
        $sales->addRow(['Gadget', 950, 7125.25]);
        $total = $sales->addRow();
        $total->addCell('Total')->style($header);
        $total->addCell('=SUM(B2:B3)')->asFormula();
        $total->addCell('=SUM(C2:C3)')->asFormula()->style($money);

        $sales->highlight('C2:C3')->greaterThan(8000)->fill($book->style()->background('C6EFCE'));
        $sales->dataBars('B2:B3', '638EC6');
        $sales->validate('B2:B3')->wholeNumber()->between(1, 100000);
        $sales->dropdown('D2:D3', ['New', 'Repeat']);
        $sales->addChart('bar')->title('Revenue')
            ->series('Sales!$C$2:$C$3', name: 'Revenue')->labels('Sales!$A$2:$A$3')->at('F2');
        $sales->addImage($png, 'png')->at('F20', [48, 48]);

        // --- a dataset on a second sheet ---
        $users = [
            ['id' => 1, 'name' => 'Omar',  'is_active' => true,  'joined' => '2026-01-15'],
            ['id' => 2, 'name' => 'Layla', 'is_active' => false, 'joined' => '2026-02-20'],
        ];
        $book->addSheet('Users')->iterate($users)
            ->columns(['id' => 'ID', 'name' => 'Name', 'is_active' => 'Active', 'joined' => 'Joined'])
            ->cast('is_active', static fn(bool $v): string => $v ? 'Yes' : 'No')
            ->cast('joined', static fn(string $v): DateTimeImmutable => new DateTimeImmutable($v))
            ->headerStyle($header)
            ->write();

        $book->save();

        // --- read it all back through the façade ---
        $in = $sheets->read($this->path);
        self::assertSame(['Sales', 'Users'], $in->sheetNames());

        self::assertEquals([
            1 => ['Product', 'Units', 'Revenue'],
            2 => ['Widget', 1200, 8400.50],
            3 => ['Gadget', 950, 7125.25],
            4 => ['Total', 'SUM(B2:B3)', 'SUM(C2:C3)'],
        ], iterator_to_array($in->sheet('Sales')->values()));

        $joined1 = (int) ExcelDate::toSerial(new DateTimeImmutable('2026-01-15'));
        $joined2 = (int) ExcelDate::toSerial(new DateTimeImmutable('2026-02-20'));
        self::assertEquals([
            ['ID' => 1, 'Name' => 'Omar',  'Active' => 'Yes', 'Joined' => $joined1],
            ['ID' => 2, 'Name' => 'Layla', 'Active' => 'No',  'Joined' => $joined2],
        ], iterator_to_array($in->sheet('Users')->records()));

        $in->close();

        // --- the package carries every part ---
        $members = $this->zipMembers($this->path);
        self::assertTrue($this->anyStartsWith($members, 'xl/charts/'), 'chart part');
        self::assertTrue($this->anyStartsWith($members, 'xl/media/'), 'image media part');

        $salesXml = $this->member($this->path, 'xl/worksheets/sheet1.xml');
        self::assertStringContainsString('conditionalFormatting', $salesXml);
        self::assertStringContainsString('dataValidation', $salesXml);
    }

    /**
     * @return list<string>
     */
    private function zipMembers(string $path): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return [];
        }
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name !== false) {
                $names[] = $name;
            }
        }
        $zip->close();

        return $names;
    }

    private function member(string $path, string $name): string
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return '';
        }
        $data = $zip->getFromName($name);
        $zip->close();

        return $data === false ? '' : $data;
    }

    /**
     * @param list<string> $members
     */
    private function anyStartsWith(array $members, string $prefix): bool
    {
        foreach ($members as $member) {
            if (str_starts_with($member, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function tempFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'sheets_showcase_');
        if ($path === false) {
            self::fail('Cannot create a temp file.');
        }

        return $path;
    }
}
