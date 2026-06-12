<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Builder;

use PHPdot\Sheets\Engine\Feature\FeatureNode;
use PHPdot\Sheets\Engine\Feature\Image\ImageNode;
use PHPdot\Sheets\Engine\Support\InvalidArgumentException;
use PHPdot\Sheets\Engine\Support\RuntimeException;

/**
 * An image being placed on a sheet — returned by {@see Sheet::addImage()}. The
 * source is a file path or raw bytes (with an explicit format); position it (and
 * optionally size it) with `->at()`. Committed to the engine when the sheet flushes.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class Image implements FeatureBuilder
{
    use CellAnchor;

    /** @var list<string> */
    private const FORMATS = ['png', 'jpg', 'jpeg', 'gif'];

    private readonly string $bytes;
    private readonly string $extension;

    private ?string $cell = null;

    /** @var array{0: int, 1: int}|null */
    private ?array $size = null;

    public function __construct(string $source, ?string $format)
    {
        if ($format !== null) {
            $this->bytes = $source;
            $this->extension = strtolower($format);
        } else {
            if (!is_file($source) || !is_readable($source)) {
                throw new InvalidArgumentException(sprintf('Image file not found or not readable: %s', $source));
            }
            $bytes = file_get_contents($source);
            if ($bytes === false) {
                throw new InvalidArgumentException(sprintf('Cannot read image file: %s', $source));
            }
            $this->bytes = $bytes;
            $this->extension = strtolower(pathinfo($source, \PATHINFO_EXTENSION));
        }

        if (!in_array($this->extension, self::FORMATS, true)) {
            throw new InvalidArgumentException(sprintf(
                'Unsupported image format "%s". Use one of: %s.',
                $this->extension,
                implode(', ', self::FORMATS),
            ));
        }
    }

    /**
     * Place the image with its top-left at an A1 cell. Size in pixels defaults to
     * the image's natural dimensions.
     *
     * @param array{0: int, 1: int}|null $size [width, height] in pixels
     */
    public function at(string $cell, ?array $size = null): self
    {
        $this->cell = $cell;
        $this->size = $size;

        return $this;
    }

    public function toFeatureNode(): FeatureNode
    {
        if ($this->cell === null) {
            throw new RuntimeException('Image needs a position — call ->at($cell).');
        }

        [$column, $row] = $this->parseCellRef($this->cell);
        [$width, $height] = $this->size ?? $this->naturalSize();

        return new ImageNode($this->bytes, $this->extension, $column, $row, $width, $height);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function naturalSize(): array
    {
        $info = getimagesizefromstring($this->bytes);
        if ($info === false) {
            throw new InvalidArgumentException(
                'Cannot determine image dimensions; pass a size to ->at($cell, [width, height]).',
            );
        }

        return [$info[0], $info[1]];
    }
}
