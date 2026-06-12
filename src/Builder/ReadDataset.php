<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Builder;

use PHPdot\Sheets\Engine\Model\ReaderInterface;
use PHPdot\Sheets\Engine\Support\InvalidArgumentException;

/**
 * Reads a sheet into clean associative records — returned by {@see ReadSheet::iterate()}.
 * `columns()` renames and selects (header label => output key), `cast()` transforms
 * a field, `map()` the whole record. `records()` streams (a generator, O(1)).
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class ReadDataset
{
    /** @var array<string, string>|null Header label => output key. */
    private ?array $columns = null;

    /** @var array<string, \Closure> Output key => per-field transform. */
    private array $casts = [];

    private ?\Closure $map = null;

    public function __construct(
        private readonly ReaderInterface $reader,
        private readonly int $index,
    ) {}

    /**
     * @param array<string, string> $columns header label => output key (also selects and orders)
     */
    public function columns(array $columns): self
    {
        $this->columns = $columns;

        return $this;
    }

    public function cast(string $field, callable $fn): self
    {
        $this->casts[$field] = $fn(...);

        return $this;
    }

    public function map(callable $fn): self
    {
        $this->map = $fn(...);

        return $this;
    }

    /**
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
