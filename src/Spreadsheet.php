<?php

declare(strict_types=1);

/**
 * Entry point for the library: open a streaming XLSX writer or reader.
 *
 * $w = Spreadsheet::writer('out.xlsx');
 * $r = Spreadsheet::reader('in.xlsx');
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Sheets;

use PHPdot\Sheets\Engine\Model\ReaderInterface;
use PHPdot\Sheets\Engine\Model\ReadOptions;
use PHPdot\Sheets\Engine\Model\WriteOptions;
use PHPdot\Sheets\Engine\Model\WriterInterface;
use PHPdot\Sheets\Engine\Xlsx\Reader;
use PHPdot\Sheets\Engine\Xlsx\Writer;

final class Spreadsheet
{
    /**
     * Prevents instantiation; use the static writer() and reader() factories.
     */
    private function __construct() {}

    /**
     * Opens a streaming XLSX writer for the given path.
     *
     * @param string $path
     * @param ?WriteOptions $options
     *
     * @return WriterInterface
     */
    public static function writer(string $path, ?WriteOptions $options = null): WriterInterface
    {
        return new Writer($path, $options);
    }

    /**
     * Opens a streaming XLSX reader for the given path.
     *
     * @param string $path
     * @param ?ReadOptions $options
     *
     * @return ReaderInterface
     */
    public static function reader(string $path, ?ReadOptions $options = null): ReaderInterface
    {
        return new Reader($path, $options);
    }
}
