<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Engine\Feature;

/**
 * Collects fragments that belong in the post-`<sheetData>` region of a worksheet.
 *
 * That region is a fixed `xsd:sequence` (CT_Worksheet) — `conditionalFormatting`
 * before `dataValidations` before `drawing`, etc. — so features hand fragments
 * here tagged with their {@see SheetTrailerOrder} rank and the codec emits them
 * in order, never in feature-execution order.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
interface TrailerSink
{
    /**
     * Add a trailing fragment at the given order rank. Fragments sharing a
     * `$group` element name (e.g. "dataValidations") are wrapped together in a
     * single `<group count="N">…</group>` container; `null` emits the fragment
     * as-is (repeatable elements like `conditionalFormatting`, or singletons).
     */
    public function add(int $order, string $xml, ?string $group = null): void;
}
