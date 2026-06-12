<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Engine\Model;

/**
 * Immutable print/page setup for a sheet: orientation, fit-to-page, header/footer
 * (centered text), print area, and repeated header rows.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class PageSetup
{
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
