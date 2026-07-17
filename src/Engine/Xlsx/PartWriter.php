<?php

declare(strict_types=1);

/**
 * A streaming sink for a single package part body, used for large parts (e.g.
 * worksheet data) so the whole part never has to be held in memory at once.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Sheets\Engine\Xlsx;

interface PartWriter
{
    /**
     * Append a chunk to the part body.
     *
     * @param string $chunk
     *
     * @return void
     */
    public function write(string $chunk): void;

    /**
     * Finish the part. No further writes are permitted afterwards.
     *
     * @return void
     */
    public function close(): void;
}
