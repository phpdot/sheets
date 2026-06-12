<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Engine\Xlsx;

use PHPdot\Sheets\Engine\Feature\StyleRegistry;
use PHPdot\Sheets\Engine\Model\Border;
use PHPdot\Sheets\Engine\Model\Style;
use PHPdot\Sheets\Engine\Support\Xml;

/**
 * Builds the deduplicated XLSX style table (`styles.xml`) and maps each registered
 * {@see Style} to a `cellXfs` index used as a cell's `s` attribute.
 *
 * Fills 0 (none) and 1 (gray125) are reserved per the OOXML convention. All state
 * is instance-scoped — no static caches.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class StyleTable implements StyleRegistry
{
    /** @var list<string> Font XML fragments; index 0 is the default font. */
    private array $fonts = ['<font/>'];

    /** @var array<string, int> Font XML => font id. */
    private array $fontIds = ['<font/>' => 0];

    /** @var list<string> Fill XML fragments; 0 = none, 1 = gray125 (reserved). */
    private array $fills = [
        '<fill><patternFill patternType="none"/></fill>',
        '<fill><patternFill patternType="gray125"/></fill>',
    ];

    /** @var array<string, int> Fill XML => fill id. */
    private array $fillIds = [];

    /** @var list<string> Border XML fragments; index 0 is the default empty border. */
    private array $borders = ['<border/>'];

    /** @var array<string, int> Border XML => border id. */
    private array $borderIds = ['<border/>' => 0];

    /** @var array<string, int> Format code => numFmt id. */
    private array $numberFormats = [];

    /** @var list<array{numFmtId: int, fontId: int, fillId: int, borderId: int, alignment: string}> cellXfs; 0 = default. */
    private array $cellXfs = [['numFmtId' => 0, 'fontId' => 0, 'fillId' => 0, 'borderId' => 0, 'alignment' => '']];

    /** @var array<string, int> Style signature => cellXfs index. */
    private array $xfIds = [];

    private int $nextNumberFormatId = 164;

    /** @var list<string> Differential format (dxf) XML fragments. */
    private array $dxfs = [];

    /** @var array<string, int> Dxf signature => index. */
    private array $dxfIds = [];

    /**
     * Register a style and return its `cellXfs` index (0 = the default style).
     */
    public function register(Style $style): int
    {
        if ($style->isEmpty()) {
            return 0;
        }

        $signature = $this->signature($style);
        if (isset($this->xfIds[$signature])) {
            return $this->xfIds[$signature];
        }

        $this->cellXfs[] = [
            'numFmtId' => $this->numberFormatId($style),
            'fontId' => $this->fontId($style),
            'fillId' => $this->fillId($style),
            'borderId' => $this->borderId($style),
            'alignment' => $this->alignmentXml($style),
        ];
        $id = count($this->cellXfs) - 1;
        $this->xfIds[$signature] = $id;

        return $id;
    }

    /**
     * Register a differential format (for conditional formatting) and return its
     * dxf index. Identical formats are deduplicated.
     */
    public function registerDxf(Style $style): int
    {
        $signature = $this->signature($style);
        if (isset($this->dxfIds[$signature])) {
            return $this->dxfIds[$signature];
        }

        $this->dxfs[] = '<dxf>' . $this->dxfFont($style) . $this->dxfFill($style) . '</dxf>';
        $id = count($this->dxfs) - 1;
        $this->dxfIds[$signature] = $id;

        return $id;
    }

    /**
     * Serialize the accumulated table to a complete `styles.xml` document.
     */
    public function toXml(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';

        if ($this->numberFormats !== []) {
            $xml .= '<numFmts count="' . count($this->numberFormats) . '">';
            foreach ($this->numberFormats as $code => $id) {
                $xml .= '<numFmt numFmtId="' . $id . '" formatCode="' . Xml::attribute($code) . '"/>';
            }
            $xml .= '</numFmts>';
        }

        $xml .= '<fonts count="' . count($this->fonts) . '">' . implode('', $this->fonts) . '</fonts>';
        $xml .= '<fills count="' . count($this->fills) . '">' . implode('', $this->fills) . '</fills>';
        $xml .= '<borders count="' . count($this->borders) . '">' . implode('', $this->borders) . '</borders>';
        $xml .= '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>';

        $xml .= '<cellXfs count="' . count($this->cellXfs) . '">';
        foreach ($this->cellXfs as $xf) {
            $xml .= '<xf numFmtId="' . $xf['numFmtId'] . '" fontId="' . $xf['fontId']
                . '" fillId="' . $xf['fillId'] . '" borderId="' . $xf['borderId'] . '" xfId="0"';
            if ($xf['fontId'] > 0) {
                $xml .= ' applyFont="1"';
            }
            if ($xf['fillId'] > 1) {
                $xml .= ' applyFill="1"';
            }
            if ($xf['numFmtId'] > 0) {
                $xml .= ' applyNumberFormat="1"';
            }
            if ($xf['borderId'] > 0) {
                $xml .= ' applyBorder="1"';
            }
            if ($xf['alignment'] !== '') {
                $xml .= ' applyAlignment="1">' . $xf['alignment'] . '</xf>';
            } else {
                $xml .= '/>';
            }
        }
        $xml .= '</cellXfs>';

        $xml .= '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>';

        if ($this->dxfs !== []) {
            $xml .= '<dxfs count="' . count($this->dxfs) . '">' . implode('', $this->dxfs) . '</dxfs>';
        }

        $xml .= '</styleSheet>';

        return $xml;
    }

    private function signature(Style $style): string
    {
        return ($style->bold ? '1' : '0')
            . ($style->italic ? '1' : '0')
            . ($style->underline ? '1' : '0')
            . ($style->wrapText ? '1' : '0')
            . '|' . ($style->fontColor !== null ? $style->fontColor->rgb : '')
            . '|' . ($style->backgroundColor !== null ? $style->backgroundColor->rgb : '')
            . '|' . ($style->numberFormat ?? '')
            . '|' . ($style->fontSize !== null ? (string) $style->fontSize : '')
            . '|' . ($style->fontName ?? '')
            . '|' . ($style->horizontalAlign !== null ? $style->horizontalAlign->value : '')
            . '|' . ($style->verticalAlign !== null ? $style->verticalAlign->value : '')
            . '|' . ($style->borders !== null ? $style->borders->signature() : '');
    }

    private function fontId(Style $style): int
    {
        if (!$style->bold && !$style->italic && !$style->underline
            && $style->fontColor === null && $style->fontSize === null && $style->fontName === null
        ) {
            return 0;
        }

        // CT_Font child order: b, i, u, sz, color, name.
        $xml = '<font>';
        if ($style->bold) {
            $xml .= '<b/>';
        }
        if ($style->italic) {
            $xml .= '<i/>';
        }
        if ($style->underline) {
            $xml .= '<u/>';
        }
        if ($style->fontSize !== null) {
            $xml .= '<sz val="' . (string) $style->fontSize . '"/>';
        }
        if ($style->fontColor !== null) {
            $xml .= '<color rgb="FF' . $style->fontColor->rgb . '"/>';
        }
        if ($style->fontName !== null) {
            $xml .= '<name val="' . Xml::attribute($style->fontName) . '"/>';
        }
        $xml .= '</font>';

        if (isset($this->fontIds[$xml])) {
            return $this->fontIds[$xml];
        }

        $this->fonts[] = $xml;
        $id = count($this->fonts) - 1;
        $this->fontIds[$xml] = $id;

        return $id;
    }

    private function fillId(Style $style): int
    {
        if ($style->backgroundColor === null) {
            return 0;
        }

        $xml = '<fill><patternFill patternType="solid"><fgColor rgb="FF'
            . $style->backgroundColor->rgb . '"/></patternFill></fill>';

        if (isset($this->fillIds[$xml])) {
            return $this->fillIds[$xml];
        }

        $this->fills[] = $xml;
        $id = count($this->fills) - 1;
        $this->fillIds[$xml] = $id;

        return $id;
    }

    private function borderId(Style $style): int
    {
        if ($style->borders === null) {
            return 0;
        }

        // CT_Border child order: left, right, top, bottom, diagonal.
        $xml = '<border>'
            . $this->edgeXml('left', $style->borders->left)
            . $this->edgeXml('right', $style->borders->right)
            . $this->edgeXml('top', $style->borders->top)
            . $this->edgeXml('bottom', $style->borders->bottom)
            . '<diagonal/></border>';

        if (isset($this->borderIds[$xml])) {
            return $this->borderIds[$xml];
        }

        $this->borders[] = $xml;
        $id = count($this->borders) - 1;
        $this->borderIds[$xml] = $id;

        return $id;
    }

    private function edgeXml(string $tag, ?Border $border): string
    {
        if ($border === null) {
            return '<' . $tag . '/>';
        }

        $xml = '<' . $tag . ' style="' . $border->style->value . '">';
        if ($border->color !== null) {
            $xml .= '<color rgb="FF' . $border->color->rgb . '"/>';
        }

        return $xml . '</' . $tag . '>';
    }

    private function alignmentXml(Style $style): string
    {
        if ($style->horizontalAlign === null && $style->verticalAlign === null && !$style->wrapText) {
            return '';
        }

        $xml = '<alignment';
        if ($style->horizontalAlign !== null) {
            $xml .= ' horizontal="' . $style->horizontalAlign->value . '"';
        }
        if ($style->verticalAlign !== null) {
            $xml .= ' vertical="' . $style->verticalAlign->value . '"';
        }
        if ($style->wrapText) {
            $xml .= ' wrapText="1"';
        }

        return $xml . '/>';
    }

    private function numberFormatId(Style $style): int
    {
        if ($style->numberFormat === null) {
            return 0;
        }

        if (isset($this->numberFormats[$style->numberFormat])) {
            return $this->numberFormats[$style->numberFormat];
        }

        $id = $this->nextNumberFormatId;
        $this->nextNumberFormatId++;
        $this->numberFormats[$style->numberFormat] = $id;

        return $id;
    }

    private function dxfFont(Style $style): string
    {
        if (!$style->bold && !$style->italic && !$style->underline && $style->fontColor === null) {
            return '';
        }

        $xml = '<font>';
        if ($style->bold) {
            $xml .= '<b/>';
        }
        if ($style->italic) {
            $xml .= '<i/>';
        }
        if ($style->underline) {
            $xml .= '<u/>';
        }
        if ($style->fontColor !== null) {
            $xml .= '<color rgb="FF' . $style->fontColor->rgb . '"/>';
        }

        return $xml . '</font>';
    }

    private function dxfFill(Style $style): string
    {
        if ($style->backgroundColor === null) {
            return '';
        }

        // Differential fills use bgColor (not fgColor) for the highlight color.
        return '<fill><patternFill><bgColor rgb="FF' . $style->backgroundColor->rgb . '"/></patternFill></fill>';
    }
}
