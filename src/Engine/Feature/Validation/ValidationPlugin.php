<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Engine\Feature\Validation;

use PHPdot\Sheets\Engine\Feature\FeaturePlugin;

/**
 * Enables conditional formatting and data validation. Pass to a writer's `use()`.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class ValidationPlugin implements FeaturePlugin
{
    public function serializers(): array
    {
        return [new ConditionalFormatSerializer(), new DataValidationSerializer()];
    }
}
