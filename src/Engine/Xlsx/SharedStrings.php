<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Engine\Xlsx;

use PHPdot\Sheets\Engine\Support\Xml;

/**
 * The shared-string table for `xl/sharedStrings.xml`. Each distinct string is
 * stored once and referenced by index, shrinking files with repeated strings.
 *
 * Opt-in (via `WriteOptions::$useSharedStrings`): it holds an in-memory map of
 * unique strings — the memory cost traded for the smaller file. The default
 * inline-string path stays O(1).
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class SharedStrings
{
    /** @var array<string, int> string => index */
    private array $indices = [];

    /** @var list<string> unique strings in index order */
    private array $strings = [];

    private int $total = 0;

    /**
     * Return the shared index for a string, adding it if new. Counts every call
     * as a reference (for the `count` attribute).
     */
    public function index(string $value): int
    {
        $this->total++;
        if (isset($this->indices[$value])) {
            return $this->indices[$value];
        }

        $index = count($this->strings);
        $this->strings[] = $value;
        $this->indices[$value] = $index;

        return $index;
    }

    public function isEmpty(): bool
    {
        return $this->strings === [];
    }

    public function toXml(): string
    {
        $items = '';
        foreach ($this->strings as $string) {
            $items .= '<si><t xml:space="preserve">' . Xml::text($string) . '</t></si>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . $this->total
            . '" uniqueCount="' . count($this->strings) . '">' . $items . '</sst>';
    }
}
