<?php

declare(strict_types=1);

/**
 * The four border edges of a cell. Any unset edge has no border.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Sheets\Engine\Model;

final class Borders
{
    /**
     * Holds the four cell-border edges; any unset edge has no border.
     *
     * @param ?Border $top
     * @param ?Border $right
     * @param ?Border $bottom
     * @param ?Border $left
     */
    public function __construct(
        public readonly ?Border $top = null,
        public readonly ?Border $right = null,
        public readonly ?Border $bottom = null,
        public readonly ?Border $left = null,
    ) {}

    /**
     * The same border on all four edges (the common "boxed cell" case).
     *
     * @param BorderStyle $style
     * @param ?Color $color
     *
     * @return self
     */
    public static function all(BorderStyle $style, ?Color $color = null): self
    {
        $edge = new Border($style, $color);

        return new self($edge, $edge, $edge, $edge);
    }

    /**
     * A stable identity for deduplication.
     *
     * @return string
     */
    public function signature(): string
    {
        return $this->edge($this->top) . '|' . $this->edge($this->right)
            . '|' . $this->edge($this->bottom) . '|' . $this->edge($this->left);
    }

    /**
     * Serializes one edge (style and color) for the signature, or empty when unset.
     *
     * @param ?Border $border
     *
     * @return string
     */
    private function edge(?Border $border): string
    {
        if ($border === null) {
            return '';
        }

        return $border->style->value . ':' . ($border->color !== null ? $border->color->rgb : '');
    }
}
