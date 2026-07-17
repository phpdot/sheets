<?php

declare(strict_types=1);

/**
 * Registers a workbook-level differential format (used by conditional formatting)
 * and returns its id. Implemented by the codec's style table.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Sheets\Engine\Feature;

use PHPdot\Sheets\Engine\Model\Style;

interface StyleRegistry
{
    /**
     * Registers a differential format (dxf) used by conditional formatting and returns its id.
     *
     * @param Style $style
     *
     * @return int
     */
    public function registerDxf(Style $style): int;
}
