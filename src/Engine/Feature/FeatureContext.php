<?php

declare(strict_types=1);

/**
 * The context handed to a {@see FeatureSerializer} at finalize time: the package
 * being assembled plus the sheet the node was attached to.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Sheets\Engine\Feature;

use PHPdot\Sheets\Engine\Xlsx\PackageBuilder;

final class FeatureContext
{
    /**
     * Bundles the package builder and the per-sheet collaborators handed to a serializer at finalize time.
     *
     * @param PackageBuilder $package
     * @param int $sheetIndex
     * @param string $sheetPartPath
     * @param DrawingCollector $drawing
     * @param TrailerSink $trailers
     * @param StyleRegistry $styles
     */
    public function __construct(
        public readonly PackageBuilder $package,
        public readonly int $sheetIndex,
        public readonly string $sheetPartPath,
        public readonly DrawingCollector $drawing,
        public readonly TrailerSink $trailers,
        public readonly StyleRegistry $styles,
    ) {}
}
