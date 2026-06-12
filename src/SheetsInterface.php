<?php

declare(strict_types=1);

namespace PHPdot\Sheets;

use PHPdot\Sheets\Builder\ReadWorkbook;
use PHPdot\Sheets\Builder\Workbook;

/**
 * The injectable entry point to the library: open a workbook for writing or
 * reading. Bound to {@see Sheets} as a container singleton, so a consumer can
 * depend on this contract rather than the concrete service.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
interface SheetsInterface
{
    /**
     * Open a workbook for writing.
     */
    public function write(string $path, bool $sharedStrings = false): Workbook;

    /**
     * Open a workbook for reading.
     */
    public function read(string $path, bool $skipEmptyRows = false): ReadWorkbook;
}
