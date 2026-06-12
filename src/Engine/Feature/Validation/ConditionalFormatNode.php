<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Engine\Feature\Validation;

use PHPdot\Sheets\Engine\Feature\Capability;
use PHPdot\Sheets\Engine\Feature\FeatureNode;
use PHPdot\Sheets\Engine\Model\Style;

/**
 * A `cellIs` conditional-formatting rule: when cells in `sqref` satisfy
 * `operator` against `formula` (and `formula2` for between/notBetween), the
 * differential `style` is applied.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class ConditionalFormatNode implements FeatureNode
{
    public function __construct(
        public readonly string $sqref,
        public readonly CfOperator $operator,
        public readonly string $formula,
        public readonly Style $style,
        public readonly ?string $formula2 = null,
    ) {}

    public function capability(): Capability
    {
        return Capability::ConditionalFormatting;
    }
}
