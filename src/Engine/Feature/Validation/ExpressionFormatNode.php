<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Engine\Feature\Validation;

use PHPdot\Sheets\Engine\Feature\Capability;
use PHPdot\Sheets\Engine\Feature\FeatureNode;
use PHPdot\Sheets\Engine\Model\Style;

/**
 * A formula-driven conditional format over `sqref`: when `formula` (relative to
 * the top-left cell, e.g. `$C1>100`) evaluates true, the differential `style`
 * is applied.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class ExpressionFormatNode implements FeatureNode
{
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
