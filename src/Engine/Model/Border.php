<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Engine\Model;

/**
 * A single border edge: its line style and an optional color (default: automatic).
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class Border
{
    public function __construct(
        public readonly BorderStyle $style,
        public readonly ?Color $color = null,
    ) {}
}
