<?php

declare(strict_types=1);

/**
 * A formula-driven conditional format over `sqref`: when `formula` (relative to
 * the top-left cell, e.g. `$C1>100`) evaluates true, the differential `style`
 * is applied.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Sheets\Engine\Feature\Validation;

use PHPdot\Sheets\Engine\Feature\Capability;
use PHPdot\Sheets\Engine\Feature\FeatureNode;
use PHPdot\Sheets\Engine\Model\Style;

final class ExpressionFormatNode implements FeatureNode
{
    /**
     * __construct.
     *
     * @param string $sqref
     * @param string $formula
     * @param Style $style
     */
    public function __construct(
        public readonly string $sqref,
        public readonly string $formula,
        public readonly Style $style,
    ) {}

    public function capability(): Capability
    {
        return Capability::ConditionalFormatting;
    }
}
