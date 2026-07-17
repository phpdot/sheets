<?php

declare(strict_types=1);

/**
 * A single border edge: its line style and an optional color (default: automatic).
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Sheets\Engine\Model;

final class Border
{
    /**
     * Holds one border edge's line style and optional color.
     *
     * @param BorderStyle $style
     * @param ?Color $color
     */
    public function __construct(
        public readonly BorderStyle $style,
        public readonly ?Color $color = null,
    ) {}
}
