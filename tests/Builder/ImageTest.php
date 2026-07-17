<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Tests\Builder;

use PHPdot\Sheets\Engine\Support\InvalidArgumentException;
use PHPdot\Sheets\Engine\Xlsx\Reader;
use PHPdot\Sheets\Sheets;
use PHPUnit\Framework\TestCase;

/**
 * Phase 4a (images): `addImage()` embeds a media part and the workbook stays
 * valid; an unsupported format throws right at `addImage()` (T-9, never a silent
 * broken embed).
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class ImageTest extends TestCase
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

    public function testImageIsEmbeddedAndTheFileStaysValid(): void
    {
        $path = $this->tempFile();
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGP4z8DwHwAFBQIAX8jx0gAAAABJRU5ErkJggg==',
            true,
        );
        if ($png === false) {
            self::fail('Bad PNG fixture.');
        }

        $book = (new Sheets())->write($path);
        $sheet = $book->addSheet('S');
        $sheet->addRow(['A', 'B']);
        $sheet->addImage($png, 'png')->at('D2', [50, 40]);
        $book->save();

        self::assertTrue($this->zipHasMedia($path), 'Image media part should be embedded.');

        $reader = new Reader($path);
        $rows = [];
        foreach ($reader->values(0) as $cells) {
            $rows[] = $cells;
        }
        $reader->close();
        self::assertSame([['A', 'B']], $rows);
    }

    public function testUnknownImageFormatThrowsAtAddImage(): void
    {
        $book = (new Sheets())->write($this->tempFile());
        $sheet = $book->addSheet('S');

        $this->expectException(InvalidArgumentException::class);
        $sheet->addImage('raw-bytes-here', 'bmp');
    }

    private function zipHasMedia(string $path): bool
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return false;
        }

        $found = false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            if (str_starts_with((string) $zip->getNameIndex($i), 'xl/media/')) {
                $found = true;

                break;
            }
        }
        $zip->close();

        return $found;
    }

    private function tempFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'sheets_image_');
        if ($path === false) {
            self::fail('Cannot create a temp file.');
        }
        $this->paths[] = $path;

        return $path;
    }
}
