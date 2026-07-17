<?php

declare(strict_types=1);

/**
 * The one entry point a developer injects: the door to a workbook.
 *
 * Stateless — `write()` and `read()` each return a fresh, per-operation object,
 * so a single instance is safe to share as a singleton (and across coroutines
 * under Swoole — verified by `bench/coroutine_safety.php`). It is deliberately
 * tiny: it only opens files. Every other factory lives on the object it owns
 * ({@see Workbook}, {@see Builder\Sheet}, {@see Builder\Row}).
 *
 * public function __construct(private SheetsInterface $sheets) {}
 * $xlsx = $this->sheets->write('report.xlsx');
 * $in   = $this->sheets->read('data.xlsx');
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Sheets;

use PHPdot\Container\Attribute\Binds;
use PHPdot\Container\Attribute\Singleton;
use PHPdot\Sheets\Builder\ReadWorkbook;
use PHPdot\Sheets\Builder\Workbook;
use PHPdot\Sheets\Engine\Model\ReadOptions;
use PHPdot\Sheets\Engine\Xlsx\Reader;

#[Singleton]
#[Binds(SheetsInterface::class)]
final class Sheets implements SheetsInterface
{
    /**
     * Open a workbook for writing.
     *
     * @param bool $sharedStrings Deduplicate strings into a shared table. Only
     *                            pays off for highly repeated strings; off by
     *                            default keeps O(1) memory (see BENCHMARK.md).
     */
    public function write(string $path, bool $sharedStrings = false): Workbook
    {
        return new Workbook($path, $sharedStrings);
    }

    /**
     * Open a workbook for reading.
     *
     * @param bool $skipEmptyRows Skip rows with no cells while iterating.
     */
    public function read(string $path, bool $skipEmptyRows = false): ReadWorkbook
    {
        return new ReadWorkbook(new Reader($path, new ReadOptions(skipEmptyRows: $skipEmptyRows)));
    }
}
