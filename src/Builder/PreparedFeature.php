<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Builder;

use PHPdot\Sheets\Engine\Feature\FeatureNode;

/**
 * Wraps an already-complete engine {@see FeatureNode} as a {@see FeatureBuilder},
 * for features that take all their input up front (data bars, color scales, icon sets).
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class PreparedFeature implements FeatureBuilder
{
    public function __construct(private readonly FeatureNode $node) {}

    public function toFeatureNode(): FeatureNode
    {
        return $this->node;
    }
}
