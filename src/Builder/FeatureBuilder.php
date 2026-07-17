<?php

declare(strict_types=1);

/**
 * A façade feature builder (image, chart, conditional format, validation rule)
 * that commits to an engine {@see FeatureNode} when its sheet flushes.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Sheets\Builder;

use PHPdot\Sheets\Engine\Feature\FeatureNode;

interface FeatureBuilder
{
    /**
     * Resolves this builder to the engine feature node the writer serializes.
     *
     * @internal
     *
     * @return FeatureNode
     */
    public function toFeatureNode(): FeatureNode;
}
