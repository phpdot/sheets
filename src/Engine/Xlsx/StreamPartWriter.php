<?php

declare(strict_types=1);

/**
 * A {@see PartWriter} backed by a file handle, used to stream large package parts
 * (e.g. worksheet bodies) to disk without buffering the whole part in memory.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Sheets\Engine\Xlsx;

use PHPdot\Sheets\Engine\Support\WriteException;

final class StreamPartWriter implements PartWriter
{
    /**
     * @var resource
     */

    private $handle;

    private bool $closed = false;

    /**
     * Wraps an open, writable stream that part chunks are written to.
     *
     * @param resource $handle An open, writable stream.
     */
    public function __construct($handle)
    {
        $this->handle = $handle;
    }

    public function write(string $chunk): void
    {
        if ($this->closed) {
            throw new WriteException('Cannot write to a closed part.');
        }

        if (fwrite($this->handle, $chunk) === false) {
            throw new WriteException('Failed to write part chunk.');
        }
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;
        fclose($this->handle);
    }
}
