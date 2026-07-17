<?php

declare(strict_types=1);

/**
 * Ordering ranks for worksheet trailing elements, following the CT_Worksheet
 * `xsd:sequence` (ECMA-376). Only relative order matters; gaps leave room.
 *
 * Sequence (excerpt, post-`sheetData`): … autoFilter → mergeCells →
 * conditionalFormatting → dataValidations → hyperlinks → … → drawing → …
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Sheets\Engine\Feature;

final class SheetTrailerOrder
{
    public const SHEET_PROTECTION = 10;
    public const AUTO_FILTER = 50;
    public const MERGE_CELLS = 100;
    public const CONDITIONAL_FORMATTING = 200;
    public const DATA_VALIDATIONS = 210;
    public const HYPERLINKS = 220;
    public const PRINT_OPTIONS = 300;
    public const PAGE_MARGINS = 310;
    public const PAGE_SETUP = 320;
    public const HEADER_FOOTER = 330;
    public const DRAWING = 400;
    public const LEGACY_DRAWING = 450;

    /**
     * Prevents instantiation; the class exposes only worksheet-trailer ordering-rank constants.
     */
    private function __construct() {}
}
