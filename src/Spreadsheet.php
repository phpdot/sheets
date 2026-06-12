<?php

declare(strict_types=1);

namespace PHPdot\Sheets;

use PHPdot\Sheets\Engine\Model\ReaderInterface;
use PHPdot\Sheets\Engine\Model\ReadOptions;
use PHPdot\Sheets\Engine\Model\WriteOptions;
use PHPdot\Sheets\Engine\Model\WriterInterface;
use PHPdot\Sheets\Engine\Xlsx\Reader;
use PHPdot\Sheets\Engine\Xlsx\Writer;

/**
 * Entry point for the library: open a streaming XLSX writer or reader.
 *
 *     $w = Spreadsheet::writer('out.xlsx');
 *     $r = Spreadsheet::reader('in.xlsx');
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class Spreadsheet
{
    private function __construct() {}

    public static function writer(string $path, ?WriteOptions $options = null): WriterInterface
    {
        return new Writer($path, $options);
    }

    public static function reader(string $path, ?ReadOptions $options = null): ReaderInterface
    {
        return new Reader($path, $options);
    }
}
