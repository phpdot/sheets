<?php

declare(strict_types=1);

/**
 * A data-bar conditional format over `sqref`: each cell shows a proportional bar
 * (min→max auto-scaled) in `color`.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Sheets\Engine\Feature\Validation;

use PHPdot\Sheets\Engine\Feature\Capability;
use PHPdot\Sheets\Engine\Feature\FeatureNode;
use PHPdot\Sheets\Engine\Model\Color;

final class DataBarNode implements FeatureNode
{
    /**
     * __construct.
     *
     * @param string $sqref
     * @param Color $color
     */
    public function __construct(
        public readonly string $sqref,
        public readonly Color $color,
    ) {}

    public function capability(): Capability
    {
        return Capability::ConditionalFormatting;
    }
}
