<?php

declare(strict_types=1);

/**
 * Highlights duplicate (or, when `$unique`, unique) values in `sqref` with the
 * differential `style`.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Sheets\Engine\Feature\Validation;

use PHPdot\Sheets\Engine\Feature\Capability;
use PHPdot\Sheets\Engine\Feature\FeatureNode;
use PHPdot\Sheets\Engine\Model\Style;

final class DuplicateValuesNode implements FeatureNode
{
    /**
     * __construct.
     *
     * @param string $sqref
     * @param Style $style
     * @param bool $unique
     */
    public function __construct(
        public readonly string $sqref,
        public readonly Style $style,
        public readonly bool $unique = false,
    ) {}

    public function capability(): Capability
    {
        return Capability::ConditionalFormatting;
    }
}
