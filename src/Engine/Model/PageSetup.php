<?php

declare(strict_types=1);

/**
 * Immutable print/page setup for a sheet: orientation, fit-to-page, header/footer
 * (centered text), print area, and repeated header rows.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Sheets\Engine\Model;

final class PageSetup
{
    /**
     * Holds the immutable print and page setup for a sheet.
     *
     * @param Orientation $orientation
     * @param ?int $fitToWidth
     * @param ?int $fitToHeight
     * @param ?string $header
     * @param ?string $footer
     * @param ?string $printArea
     * @param ?int $repeatRows
     */
    public function __construct(
        public readonly Orientation $orientation = Orientation::Portrait,
        public readonly ?int $fitToWidth = null,
        public readonly ?int $fitToHeight = null,
        public readonly ?string $header = null,
        public readonly ?string $footer = null,
        public readonly ?string $printArea = null,
        public readonly ?int $repeatRows = null,
    ) {}
}
