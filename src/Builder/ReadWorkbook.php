<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Builder;

use PHPdot\Sheets\Engine\Model\ReaderInterface;
use PHPdot\Sheets\Engine\Model\SheetInfo;
use PHPdot\Sheets\Engine\Support\ReadException;

/**
 * A workbook opened for reading — returned by {@see \PHPdot\Sheets\Sheets::read()}. Select a
 * sheet by name or index, then read its rows/values/records.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class ReadWorkbook
{
    public function __construct(private readonly ReaderInterface $reader) {}

    /**
     * Select a sheet by name or 0-based index.
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
     * @return list<string>
     */
    public function sheetNames(): array
    {
        return array_map(static fn(SheetInfo $info): string => $info->name, $this->reader->sheets());
    }

    public function close(): void
    {
        $this->reader->close();
    }

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
