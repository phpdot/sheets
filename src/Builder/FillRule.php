<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Builder;

use PHPdot\Sheets\Engine\Feature\FeatureNode;
use PHPdot\Sheets\Engine\Model\Style;
use PHPdot\Sheets\Engine\Support\RuntimeException;

/**
 * A conditional-formatting rule whose only remaining choice is its fill — used by
 * {@see Sheet::expression()}, {@see Sheet::duplicates()}, {@see Sheet::uniques()}.
 * The engine node is produced from the fill style when the sheet flushes.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class FillRule implements FeatureBuilder
{
    private ?Style $style = null;

    /** @var \Closure(Style): FeatureNode */
    private readonly \Closure $build;

    /**
     * @param \Closure(Style): FeatureNode $build
     */
    public function __construct(\Closure $build)
    {
        $this->build = $build;
    }

    public function fill(Style $style): self
    {
        $this->style = $style;

        return $this;
    }

    public function toFeatureNode(): FeatureNode
    {
        if ($this->style === null) {
            throw new RuntimeException('This conditional format needs ->fill($style).');
        }

        return ($this->build)($this->style);
    }
}
