<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Tests\Engine\Xlsx;

use PHPdot\Sheets\Engine\Model\WriteOptions;
use PHPdot\Sheets\Spreadsheet;
use PHPUnit\Framework\TestCase;

final class SharedStringsTest extends TestCase
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

    public function testSharedStringsModeDeduplicates(): void
    {
        $path = $this->newPath();
        $writer = Spreadsheet::writer($path, new WriteOptions(useSharedStrings: true));
        $writer->startSheet('S');
        $writer->addRow(['Apple', 'Banana']);
        $writer->addRow(['Apple', 'Cherry']); // "Apple" repeats
        $writer->close();

        // 4 string references, 3 unique entries.
        $sst = $this->member($path, 'xl/sharedStrings.xml');
        self::assertStringContainsString('count="4" uniqueCount="3"', $sst);
        self::assertSame(3, substr_count($sst, '<si>'));

        // Cells reference the table, not inline strings.
        $sheet = $this->member($path, 'xl/worksheets/sheet1.xml');
        self::assertStringContainsString('t="s"', $sheet);
        self::assertStringNotContainsString('inlineStr', $sheet);
    }

    public function testSharedStringsRoundTripExactly(): void
    {
        $input = [['Apple', 'Banana'], ['Apple', 'Cherry'], ['Date', 42]];

        $path = $this->newPath();
        $writer = Spreadsheet::writer($path, new WriteOptions(useSharedStrings: true));
        $writer->startSheet('S');
        foreach ($input as $row) {
            $writer->addRow($row);
        }
        $writer->close();

        $reader = Spreadsheet::reader($path);
        $out = [];
        foreach ($reader->values() as $row) {
            $out[] = $row;
        }
        $reader->close();

        self::assertSame($input, $out);
    }

    public function testInlineStringsByDefault(): void
    {
        $path = $this->newPath();
        $writer = Spreadsheet::writer($path);
        $writer->startSheet('S');
        $writer->addRow(['Apple', 'Banana']);
        $writer->close();

        self::assertFalse($this->hasMember($path, 'xl/sharedStrings.xml'));
        self::assertStringContainsString('t="inlineStr"', $this->member($path, 'xl/worksheets/sheet1.xml'));
    }

    private function newPath(): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'sheets_sst_');
        $this->tempFiles[] = $path;

        return $path;
    }

    private function member(string $archive, string $name): string
    {
        $zip = new \ZipArchive();
        if ($zip->open($archive) !== true) {
            self::fail(sprintf('Cannot open archive: %s', $archive));
        }
        $data = $zip->getFromName($name);
        $zip->close();
        if ($data === false) {
            self::fail(sprintf('Member not found: %s', $name));
        }

        return $data;
    }

    private function hasMember(string $archive, string $name): bool
    {
        $zip = new \ZipArchive();
        if ($zip->open($archive) !== true) {
            return false;
        }
        $found = $zip->locateName($name) !== false;
        $zip->close();

        return $found;
    }
}
