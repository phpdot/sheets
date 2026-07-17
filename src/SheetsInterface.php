<?php

declare(strict_types=1);

/**
 * The injectable entry point to the library: open a workbook for writing or
 * reading. Bound to {@see Sheets} as a container singleton, so a consumer can
 * depend on this contract rather than the concrete service.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Sheets;

use PHPdot\Sheets\Builder\ReadWorkbook;
use PHPdot\Sheets\Builder\Workbook;

interface SheetsInterface
{
    /**
     * Open a workbook for writing.
     *
     * @param string $path
     * @param bool $sharedStrings
     *
     * @return Workbook
     */
    public function write(string $path, bool $sharedStrings = false): Workbook;

    /**
     * Open a workbook for reading.
     *
     * @param bool $skipEmptyRows
     * @param string $path
     *
     * @return ReadWorkbook
     */
    public function read(string $path, bool $skipEmptyRows = false): ReadWorkbook;
}
