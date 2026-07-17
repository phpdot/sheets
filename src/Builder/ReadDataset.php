<?php

declare(strict_types=1);

/**
 * Reads a sheet into clean associative records — returned by {@see ReadSheet::iterate()}.
 * `columns()` renames and selects (header label => output key), `cast()` transforms
 * a field, `map()` the whole record. `records()` streams (a generator, O(1)).
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Sheets\Builder;

use PHPdot\Sheets\Engine\Model\ReaderInterface;
use PHPdot\Sheets\Engine\Support\InvalidArgumentException;

final class ReadDataset
{
    /**
     * @var array<string, string>|null Header label => output key.
     */

    private ?array $columns = null;

    /**
     * @var array<string, \Closure> Output key => per-field transform.
     */

    private array $casts = [];

    private ?\Closure $map = null;

    /**
     * Wraps a reader and the 0-based sheet index the records are drawn from.
     *
     * @param ReaderInterface $reader
     * @param int $index
     */
    public function __construct(
        private readonly ReaderInterface $reader,
        private readonly int $index,
    ) {}

    /**
     * Renames and selects the output columns (header label => output key).
     *
     * @param array<string, string> $columns header label => output key (also selects and orders)
     *
     * @return self
     */
    public function columns(array $columns): self
    {
        $this->columns = $columns;

        return $this;
    }

    /**
     * Transforms one field of each record as it is read.
     *
     * @param string $field
     * @param callable $fn
     *
     * @return self
     */
    public function cast(string $field, callable $fn): self
    {
        $this->casts[$field] = $fn(...);

        return $this;
    }

    /**
     * Transforms the whole record, or returns null to skip it.
     *
     * @param callable $fn
     *
     * @return self
     */
    public function map(callable $fn): self
    {
        $this->map = $fn(...);

        return $this;
    }

    /**
     * Streams the sheet as clean associative records, one at a time.
     *
     * @return \Generator<int, array<array-key, mixed>>
     */
    public function records(): \Generator
    {
        $header = null;
        foreach ($this->reader->values($this->index) as $cells) {
            if ($header === null) {
                $header = array_map(static fn($value): string => (string) $value, $cells);

                continue;
            }

            $raw = [];
            foreach ($header as $i => $label) {
                $raw[$label] = $cells[$i] ?? null;
            }

            if ($this->columns !== null) {
                $record = [];
                foreach ($this->columns as $label => $key) {
                    $record[$key] = $raw[$label] ?? null;
                }
            } else {
                $record = $raw;
            }

            foreach ($this->casts as $key => $fn) {
                if (array_key_exists($key, $record)) {
                    $record[$key] = $fn($record[$key], $record);
                }
            }

            if ($this->map !== null) {
                $mapped = ($this->map)($record);
                if ($mapped === null) {
                    continue;
                }
                if (!is_array($mapped)) {
                    throw new InvalidArgumentException('map() must return the record array, or null to skip it.');
                }
                $record = $mapped;
            }

            yield $record;
        }
    }
}
