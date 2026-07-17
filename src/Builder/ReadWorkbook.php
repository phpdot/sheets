<?php

declare(strict_types=1);

/**
 * A workbook opened for reading — returned by {@see \PHPdot\Sheets\Sheets::read()}. Select a
 * sheet by name or index, then read its rows/values/records.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Sheets\Builder;

use PHPdot\Sheets\Engine\Model\ReaderInterface;
use PHPdot\Sheets\Engine\Model\SheetInfo;
use PHPdot\Sheets\Engine\Support\ReadException;

final class ReadWorkbook
{
    /**
     * Wraps the underlying reader for the opened workbook.
     *
     * @param ReaderInterface $reader
     */
    public function __construct(private readonly ReaderInterface $reader) {}

    /**
     * Select a sheet by name or 0-based index.
     *
     * @param string|int $sheet
     *
     * @return ReadSheet
     */
    public function sheet(string|int $sheet): ReadSheet
    {
        return new ReadSheet($this->reader, is_int($sheet) ? $sheet : $this->indexOf($sheet));
    }

    /**
     * Metadata for every sheet, in workbook order.
     *
     * @return list<SheetInfo>
     */
    public function sheets(): array
    {
        return $this->reader->sheets();
    }

    /**
     * Returns every sheet name, in workbook order.
     *
     * @return list<string>
     */
    public function sheetNames(): array
    {
        return array_map(static fn(SheetInfo $info): string => $info->name, $this->reader->sheets());
    }

    /**
     * Closes the reader and releases the underlying file handle.
     *
     * @return void
     */
    public function close(): void
    {
        $this->reader->close();
    }

    /**
     * Resolves a sheet name to its 0-based index, or throws if absent.
     *
     * @param string $name
     *
     * @return int
     */
    private function indexOf(string $name): int
    {
        foreach ($this->reader->sheets() as $info) {
            if ($info->name === $name) {
                return $info->index;
            }
        }

        throw new ReadException(sprintf('No sheet named "%s" in the workbook.', $name));
    }
}
