<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Engine\Feature;

use PHPdot\Sheets\Engine\Xlsx\PackageBuilder;

/**
 * The context handed to a {@see FeatureSerializer} at finalize time: the package
 * being assembled plus the sheet the node was attached to.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class FeatureContext
{
    public function __construct(
        public readonly PackageBuilder $package,
        public readonly int $sheetIndex,
        public readonly string $sheetPartPath,
        public readonly DrawingCollector $drawing,
        public readonly TrailerSink $trailers,
        public readonly StyleRegistry $styles,
    ) {}
}
