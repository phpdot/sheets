<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Engine\Feature\Validation;

use PHPdot\Sheets\Engine\Feature\Capability;
use PHPdot\Sheets\Engine\Feature\FeatureNode;

/**
 * An icon-set conditional format over `sqref`: each cell gets an icon based on
 * its value's position across evenly-spaced percentage thresholds.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class IconSetNode implements FeatureNode
{
    public function __construct(
        public readonly string $sqref,
        public readonly IconSet $iconSet,
    ) {}

    public function capability(): Capability
    {
        return Capability::ConditionalFormatting;
    }
}
