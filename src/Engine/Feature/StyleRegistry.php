<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Engine\Feature;

use PHPdot\Sheets\Engine\Model\Style;

/**
 * Registers a workbook-level differential format (used by conditional formatting)
 * and returns its id. Implemented by the codec's style table.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
interface StyleRegistry
{
    public function registerDxf(Style $style): int;
}
