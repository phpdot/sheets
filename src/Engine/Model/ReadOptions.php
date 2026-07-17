<?php

declare(strict_types=1);

/**
 * Immutable options governing a read operation.
 *
 * The `max*` limits are zip-bomb protection for reading untrusted files. They
 * are deliberately generous — a legitimately large export passes untouched,
 * because a real file is big in its *streamed* row data (never held whole),
 * while a bomb betrays itself through an absurd compression ratio or an
 * oversized whole-read part. Set any limit to `0` to disable that check when
 * reading files you fully trust.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Sheets\Engine\Model;

final class ReadOptions
{
    /**
     * Configures a read, with generous zip-bomb limits that a legitimate file passes untouched.
     *
     * @param bool $skipEmptyRows Skip rows with no cells while iterating.
     * @param int $maxCompressionRatio Reject any archive part whose decompressed/compressed
     *                                 ratio exceeds this (DEFLATE's physical ceiling is ~1032:1;
     *                                 real spreadsheet XML is ≤~50:1 even when uniform, so 200 sits
     *                                 safely between). `0` disables the ratio gate.
     * @param int $ratioFloorBytes Parts smaller than this (decompressed) are exempt from the
     *                             ratio check — tiny parts have noisy ratios and cost nothing.
     * @param int $maxWholeReadBytes Cap (bytes) on a single part read whole into memory
     *                               (styles, workbook, rels, comments). These are format-bounded,
     *                               so the default is ~1000× any real file. `0` disables.
     * @param int $maxSharedStringBytes Cap (bytes) on the accumulated shared-string table — the one
     *                                  part that scales with data yet is held in memory. `0` disables.
     */
    public function __construct(
        public readonly bool $skipEmptyRows = false,
        public readonly int $maxCompressionRatio = 200,
        public readonly int $ratioFloorBytes = 1_048_576,
        public readonly int $maxWholeReadBytes = 67_108_864,
        public readonly int $maxSharedStringBytes = 268_435_456,
    ) {}
}
