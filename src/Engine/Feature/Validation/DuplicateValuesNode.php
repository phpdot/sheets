<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Engine\Feature\Validation;

use PHPdot\Sheets\Engine\Feature\Capability;
use PHPdot\Sheets\Engine\Feature\FeatureNode;
use PHPdot\Sheets\Engine\Model\Style;

/**
 * Highlights duplicate (or, when `$unique`, unique) values in `sqref` with the
 * differential `style`.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class DuplicateValuesNode implements FeatureNode
{
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
