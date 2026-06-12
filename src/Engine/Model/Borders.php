<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Engine\Model;

/**
 * The four border edges of a cell. Any unset edge has no border.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class Borders
{
    public function __construct(
        public readonly ?Border $top = null,
        public readonly ?Border $right = null,
        public readonly ?Border $bottom = null,
        public readonly ?Border $left = null,
    ) {}

    /**
     * The same border on all four edges (the common "boxed cell" case).
     */
    public static function all(BorderStyle $style, ?Color $color = null): self
    {
        $edge = new Border($style, $color);

        return new self($edge, $edge, $edge, $edge);
    }

    /**
     * A stable identity for deduplication.
     */
    public function signature(): string
    {
        return $this->edge($this->top) . '|' . $this->edge($this->right)
            . '|' . $this->edge($this->bottom) . '|' . $this->edge($this->left);
    }

    private function edge(?Border $border): string
    {
        if ($border === null) {
            return '';
        }

        return $border->style->value . ':' . ($border->color !== null ? $border->color->rgb : '');
    }
}
