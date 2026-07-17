<?php

declare(strict_types=1);

/**
 * Wraps an already-complete engine {@see FeatureNode} as a {@see FeatureBuilder},
 * for features that take all their input up front (data bars, color scales, icon sets).
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Sheets\Builder;

use PHPdot\Sheets\Engine\Feature\FeatureNode;

final class PreparedFeature implements FeatureBuilder
{
    /**
     * Wraps an already-built engine feature node.
     *
     * @param FeatureNode $node
     */
    public function __construct(private readonly FeatureNode $node) {}

    public function toFeatureNode(): FeatureNode
    {
        return $this->node;
    }
}
