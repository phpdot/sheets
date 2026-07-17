<?php

declare(strict_types=1);

/**
 * Handle returned when an image or chart is embedded in a sheet's drawing: the
 * relationship id to reference from the anchor markup (`r:embed` for a picture,
 * `r:id` for a chart) and a unique object id for the drawing object.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Sheets\Engine\Feature;

final class DrawingObject
{
    /**
     * Holds the embed handle for one drawing object: its relationship id and object id.
     *
     * @param string $relationshipId
     * @param int $id
     */
    public function __construct(
        public readonly string $relationshipId,
        public readonly int $id,
    ) {}
}
