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
        public readonly null|int $fitToWidth = null,
        public readonly null|int $fitToHeight = null,
        public readonly null|string $header = null,
        public readonly null|string $footer = null,
        public readonly null|string $printArea = null,
        public readonly null|int $repeatRows = null,
    ) {}
}
