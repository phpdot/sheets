<?php

declare(strict_types=1);

/**
 * A format-neutral image placed on a sheet: the raw bytes, its format, the
 * top-left anchor cell (0-based column/row) and a pixel size.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Sheets\Engine\Feature\Image;

use PHPdot\Sheets\Engine\Feature\Capability;
use PHPdot\Sheets\Engine\Feature\FeatureNode;
use PHPdot\Sheets\Engine\Support\InvalidArgumentException;

final class ImageNode implements FeatureNode
{
    /**
     * Holds a decoded image and the cell anchor and pixel size at which it is drawn.
     *
     * @param string $bytes
     * @param string $extension
     * @param int $column
     * @param int $row
     * @param int $widthPx
     * @param int $heightPx
     */
    public function __construct(
        public readonly string $bytes,
        public readonly string $extension,
        public readonly int $column,
        public readonly int $row,
        public readonly int $widthPx,
        public readonly int $heightPx,
    ) {}

    /**
     * Build an image node by reading a file from disk.
     *
     * @param string $path
     * @param int $column
     * @param int $row
     * @param int $widthPx
     * @param int $heightPx
     *
     * @throws InvalidArgumentException When the file cannot be read.
     *
     * @return self
     */
    public static function fromFile(string $path, int $column, int $row, int $widthPx, int $heightPx): self
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new InvalidArgumentException(sprintf('Image file not found or not readable: %s', $path));
        }

        $bytes = file_get_contents($path);
        if ($bytes === false) {
            throw new InvalidArgumentException(sprintf('Cannot read image file: %s', $path));
        }

        return new self($bytes, strtolower(pathinfo($path, \PATHINFO_EXTENSION)), $column, $row, $widthPx, $heightPx);
    }

    public function capability(): Capability
    {
        return Capability::Images;
    }
}
