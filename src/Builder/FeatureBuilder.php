<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Builder;

use PHPdot\Sheets\Engine\Feature\FeatureNode;

/**
 * A façade feature builder (image, chart, conditional format, validation rule)
 * that commits to an engine {@see FeatureNode} when its sheet flushes.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
interface FeatureBuilder
{
    /**
     * @internal Resolve to the engine node the writer serializes.
     */
    public function toFeatureNode(): FeatureNode;
}
