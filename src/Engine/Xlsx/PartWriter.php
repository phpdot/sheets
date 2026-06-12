<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Engine\Xlsx;

/**
 * A streaming sink for a single package part body, used for large parts (e.g.
 * worksheet data) so the whole part never has to be held in memory at once.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
interface PartWriter
{
    /**
     * Append a chunk to the part body.
     */
    public function write(string $chunk): void;

    /**
     * Finish the part. No further writes are permitted afterwards.
     */
    public function close(): void;
}
