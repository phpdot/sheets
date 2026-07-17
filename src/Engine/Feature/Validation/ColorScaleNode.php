<?php

declare(strict_types=1);

/**
 * A color-scale conditional format over `sqref`: a gradient from `minColor` to
 * `maxColor`, optionally through `midColor` (a 3-color scale).
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Sheets\Engine\Feature\Validation;

use PHPdot\Sheets\Engine\Feature\Capability;
use PHPdot\Sheets\Engine\Feature\FeatureNode;
use PHPdot\Sheets\Engine\Model\Color;

final class ColorScaleNode implements FeatureNode
{
    /**
     * __construct.
     *
     * @param string $sqref
     * @param Color $minColor
     * @param Color $maxColor
     * @param ?Color $midColor
     */
    public function __construct(
        public readonly string $sqref,
        public readonly Color $minColor,
        public readonly Color $maxColor,
        public readonly ?Color $midColor = null,
    ) {}

    public function capability(): Capability
    {
        return Capability::ConditionalFormatting;
    }
}
