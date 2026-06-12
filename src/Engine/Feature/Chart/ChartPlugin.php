<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Engine\Feature\Chart;

use PHPdot\Sheets\Engine\Feature\FeaturePlugin;

/**
 * The chart feature: pass to a writer's `use()` to enable embedding charts.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class ChartPlugin implements FeaturePlugin
{
    public function serializers(): array
    {
        return [new ChartSerializer()];
    }
}
