<?php

declare(strict_types=1);

/**
 * An icon-set conditional format over `sqref`: each cell gets an icon based on
 * its value's position across evenly-spaced percentage thresholds.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Sheets\Engine\Feature\Validation;

use PHPdot\Sheets\Engine\Feature\Capability;
use PHPdot\Sheets\Engine\Feature\FeatureNode;

final class IconSetNode implements FeatureNode
{
    /**
     * __construct.
     *
     * @param string $sqref
     * @param IconSet $iconSet
     */
    public function __construct(
        public readonly string $sqref,
        public readonly IconSet $iconSet,
    ) {}

    public function capability(): Capability
    {
        return Capability::ConditionalFormatting;
    }
}
