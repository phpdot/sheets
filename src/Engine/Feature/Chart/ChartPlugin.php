<?php

declare(strict_types=1);

/**
 * The chart feature: pass to a writer's `use()` to enable embedding charts.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Sheets\Engine\Feature\Chart;

use PHPdot\Sheets\Engine\Feature\FeaturePlugin;

final class ChartPlugin implements FeaturePlugin
{
    public function serializers(): array
    {
        return [new ChartSerializer()];
    }
}
