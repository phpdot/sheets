<?php

declare(strict_types=1);

/**
 * Parses `styles.xml` back into {@see Style} objects keyed by `cellXfs` index —
 * the inverse of the writer's `StyleTable`, so styles written can be read back.
 *
 * Theme/indexed colors are not resolved (only explicit `rgb`); such fonts/fills
 * come back without a color.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Sheets\Engine\Xlsx;

use PHPdot\Sheets\Engine\Model\Border;
use PHPdot\Sheets\Engine\Model\Borders;
use PHPdot\Sheets\Engine\Model\BorderStyle;
use PHPdot\Sheets\Engine\Model\Color;
use PHPdot\Sheets\Engine\Model\HorizontalAlign;
use PHPdot\Sheets\Engine\Model\Style;
use PHPdot\Sheets\Engine\Model\VerticalAlign;

final class StyleReader
{
    /**
     * @var array<int, string> A few common built-in number formats.
     */

    private const BUILTIN_FORMATS = [
        1 => '0', 2 => '0.00', 3 => '#,##0', 4 => '#,##0.00',
        9 => '0%', 10 => '0.00%', 14 => 'mm-dd-yy', 49 => '@',
    ];

    /**
     * Parses a styles.xml document into Style objects keyed by their cellXfs index.
     *
     * @param string $xml
     *
     * @return array<int, Style> cellXfs index => Style
     */
    public function parse(string $xml): array
    {
        $previous = libxml_use_internal_errors(true);
        $root = simplexml_load_string($xml, \SimpleXMLElement::class, \LIBXML_NONET);
        libxml_use_internal_errors($previous);
        if ($root === false) {
            return [];
        }

        $fonts = $this->fonts($root);
        $fills = $this->fills($root);
        $borders = $this->borders($root);
        $numberFormats = $this->numberFormats($root);

        $styles = [];
        if (isset($root->cellXfs->xf)) {
            $index = 0;
            foreach ($root->cellXfs->xf as $xf) {
                $styles[$index] = $this->assemble($xf, $fonts, $fills, $borders, $numberFormats);
                $index++;
            }
        }

        return $styles;
    }

    /**
     * Assembles one cellXf into a Style, resolving its font, fill, border, format and alignment.
     *
     * @param \SimpleXMLElement $xf
     * @param array<int, array{bold: bool, italic: bool, underline: bool, size: float|null,
     *     name: string|null, color: Color|null}> $fonts
     * @param array<int, Color|null> $fills
     * @param array<int, Borders|null> $borders
     * @param array<int, string> $numberFormats
     *
     * @return Style
     */
    private function assemble(\SimpleXMLElement $xf, array $fonts, array $fills, array $borders, array $numberFormats): Style
    {
        $font = $fonts[(int) $xf['fontId']] ?? ['bold' => false, 'italic' => false, 'underline' => false, 'size' => null, 'name' => null, 'color' => null];
        $numberFormatId = (int) $xf['numFmtId'];
        $numberFormat = $numberFormats[$numberFormatId] ?? (self::BUILTIN_FORMATS[$numberFormatId] ?? null);

        $horizontal = null;
        $vertical = null;
        $wrap = false;
        if (isset($xf->alignment)) {
            $horizontal = HorizontalAlign::tryFrom((string) $xf->alignment['horizontal']);
            $vertical = VerticalAlign::tryFrom((string) $xf->alignment['vertical']);
            $wrap = (string) $xf->alignment['wrapText'] === '1';
        }

        return new Style(
            bold: $font['bold'],
            italic: $font['italic'],
            underline: $font['underline'],
            fontColor: $font['color'],
            backgroundColor: $fills[(int) $xf['fillId']] ?? null,
            numberFormat: $numberFormat,
            fontSize: $font['size'],
            fontName: $font['name'],
            horizontalAlign: $horizontal,
            verticalAlign: $vertical,
            wrapText: $wrap,
            borders: $borders[(int) $xf['borderId']] ?? null,
        );
    }

    /**
     * Parses the <fonts> table into indexed font-property maps.
     *
     * @param \SimpleXMLElement $root
     *
     * @return array<int, array{bold: bool, italic: bool, underline: bool, size: float|null,
     *     name: string|null, color: Color|null}>
     */
    private function fonts(\SimpleXMLElement $root): array
    {
        $fonts = [];
        if (!isset($root->fonts->font)) {
            return $fonts;
        }

        $index = 0;
        foreach ($root->fonts->font as $font) {
            $fonts[$index] = [
                'bold' => isset($font->b),
                'italic' => isset($font->i),
                'underline' => isset($font->u),
                'size' => isset($font->sz) ? (float) (string) $font->sz['val'] : null,
                'name' => isset($font->name) ? (string) $font->name['val'] : null,
                'color' => isset($font->color) ? $this->color((string) $font->color['rgb']) : null,
            ];
            $index++;
        }

        return $fonts;
    }

    /**
     * Parses the <fills> table into indexed background colors.
     *
     * @param \SimpleXMLElement $root
     *
     * @return array<int, Color|null>
     */
    private function fills(\SimpleXMLElement $root): array
    {
        $fills = [];
        if (!isset($root->fills->fill)) {
            return $fills;
        }

        $index = 0;
        foreach ($root->fills->fill as $fill) {
            $color = null;
            if (isset($fill->patternFill->fgColor)) {
                $color = $this->color((string) $fill->patternFill->fgColor['rgb']);
            }
            $fills[$index] = $color;
            $index++;
        }

        return $fills;
    }

    /**
     * Parses the <borders> table into indexed Borders objects.
     *
     * @param \SimpleXMLElement $root
     *
     * @return array<int, Borders|null>
     */
    private function borders(\SimpleXMLElement $root): array
    {
        $borders = [];
        if (!isset($root->borders->border)) {
            return $borders;
        }

        $index = 0;
        foreach ($root->borders->border as $border) {
            $top = $this->edge($border->top);
            $right = $this->edge($border->right);
            $bottom = $this->edge($border->bottom);
            $left = $this->edge($border->left);
            $borders[$index] = ($top ?? $right ?? $bottom ?? $left) !== null
                ? new Borders($top, $right, $bottom, $left)
                : null;
            $index++;
        }

        return $borders;
    }

    /**
     * Reads one border-edge element into a Border, or null when the edge is unstyled.
     *
     * @param ?\SimpleXMLElement $edge
     *
     * @return ?Border
     */
    private function edge(?\SimpleXMLElement $edge): ?Border
    {
        if ($edge === null) {
            return null;
        }
        $style = BorderStyle::tryFrom((string) $edge['style']);
        if ($style === null) {
            return null;
        }

        return new Border($style, isset($edge->color) ? $this->color((string) $edge->color['rgb']) : null);
    }

    /**
     * Parses the <numFmts> table into a map of format id to format code.
     *
     * @param \SimpleXMLElement $root
     *
     * @return array<int, string>
     */
    private function numberFormats(\SimpleXMLElement $root): array
    {
        $formats = [];
        if (isset($root->numFmts->numFmt)) {
            foreach ($root->numFmts->numFmt as $numFmt) {
                $formats[(int) $numFmt['numFmtId']] = (string) $numFmt['formatCode'];
            }
        }

        return $formats;
    }

    /**
     * Parses an ARGB or RGB hex string into a Color, or null when blank or malformed.
     *
     * @param string $rgb
     *
     * @return ?Color
     */
    private function color(string $rgb): ?Color
    {
        if ($rgb === '') {
            return null;
        }
        if (strlen($rgb) === 8) {
            $rgb = substr($rgb, 2);
        }
        if (strlen($rgb) !== 6) {
            return null;
        }

        return Color::hex('#' . $rgb);
    }
}
