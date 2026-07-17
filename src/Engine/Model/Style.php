<?php

declare(strict_types=1);

/**
 * An immutable cell style: font (emphasis, size, family, color), fill, number
 * format, alignment, and borders.
 *
 * Format-neutral — the codec's style serializer translates it to its own markup.
 * Registered once per writer via `registerStyle()` and referenced by integer id.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Sheets\Engine\Model;

use PHPdot\Sheets\Engine\Support\InvalidArgumentException;

final class Style
{
    /**
     * Holds the full set of immutable style attributes; every field defaults to unset.
     *
     * @param bool $bold
     * @param bool $italic
     * @param bool $underline
     * @param ?Color $fontColor
     * @param ?Color $backgroundColor
     * @param ?string $numberFormat
     * @param ?float $fontSize
     * @param ?string $fontName
     * @param ?HorizontalAlign $horizontalAlign
     * @param ?VerticalAlign $verticalAlign
     * @param bool $wrapText
     * @param ?Borders $borders
     */
    public function __construct(
        public readonly bool $bold = false,
        public readonly bool $italic = false,
        public readonly bool $underline = false,
        public readonly ?Color $fontColor = null,
        public readonly ?Color $backgroundColor = null,
        public readonly ?string $numberFormat = null,
        public readonly ?float $fontSize = null,
        public readonly ?string $fontName = null,
        public readonly ?HorizontalAlign $horizontalAlign = null,
        public readonly ?VerticalAlign $verticalAlign = null,
        public readonly bool $wrapText = false,
        public readonly ?Borders $borders = null,
    ) {}

    /**
     * Start a fresh style to chain on: `Style::make()->bold()->fontColor('FFFFFF')`.
     *
     * @return self
     */
    public static function make(): self
    {
        return new self();
    }

    /**
     * Returns a copy with bold emphasis toggled (a fluent alias for withBold()).
     *
     * @param bool $bold
     *
     * @return self
     */
    public function bold(bool $bold = true): self
    {
        return $this->withBold($bold);
    }

    /**
     * Returns a copy with italic emphasis toggled (a fluent alias for withItalic()).
     *
     * @param bool $italic
     *
     * @return self
     */
    public function italic(bool $italic = true): self
    {
        return $this->withItalic($italic);
    }

    /**
     * Returns a copy with underline toggled (a fluent alias for withUnderline()).
     *
     * @param bool $underline
     *
     * @return self
     */
    public function underline(bool $underline = true): self
    {
        return $this->withUnderline($underline);
    }

    /**
     * Returns a copy with the font size (in points) set (alias for withFontSize()).
     *
     * @param float $points
     *
     * @return self
     */
    public function fontSize(float $points): self
    {
        return $this->withFontSize($points);
    }

    /**
     * Returns a copy with the font family name set (alias for withFontName()).
     *
     * @param string $name
     *
     * @return self
     */
    public function fontName(string $name): self
    {
        return $this->withFontName($name);
    }

    /**
     * Font color as a {@see Color} or a hex string ("FF0000", "#FF0000", "f00").
     *
     * @param Color|string $color
     *
     * @return Style
     */
    public function fontColor(Color|string $color): self
    {
        return $this->withFontColor($this->toColor($color));
    }

    /**
     * Background fill as a {@see Color} or a hex string.
     *
     * @param Color|string $color
     *
     * @return Style
     */
    public function background(Color|string $color): self
    {
        return $this->withBackgroundColor($this->toColor($color));
    }

    /**
     * Returns a copy with the number format resolved from a preset name or raw code.
     *
     * @param string $code
     *
     * @return self
     */
    public function numberFormat(string $code): self
    {
        return $this->withNumberFormat(NumberFormats::resolve($code));
    }

    /**
     * Horizontal alignment: a {@see HorizontalAlign} or one of
     * "left", "center", "right", "fill", "justify".
     *
     * @param HorizontalAlign|string $align
     *
     * @return Style
     */
    public function align(HorizontalAlign|string $align): self
    {
        return $this->withHorizontalAlign(
            $align instanceof HorizontalAlign ? $align : $this->toHorizontalAlign($align),
        );
    }

    /**
     * Vertical alignment: a {@see VerticalAlign} or one of "top", "middle"/"center", "bottom".
     *
     * @param VerticalAlign|string $align
     *
     * @return Style
     */
    public function valign(VerticalAlign|string $align): self
    {
        return $this->withVerticalAlign(
            $align instanceof VerticalAlign ? $align : $this->toVerticalAlign($align),
        );
    }

    /**
     * Returns a copy with text wrapping toggled (a fluent alias for withWrapText()).
     *
     * @param bool $wrap
     *
     * @return self
     */
    public function wrap(bool $wrap = true): self
    {
        return $this->withWrapText($wrap);
    }

    /**
     * One border on all four edges: a {@see BorderStyle} or one of
     * "thin", "medium", "thick", "dashed", "dotted", "double", with an optional color.
     *
     * @param BorderStyle|string $style
     * @param Color|string|null $color
     *
     * @return Style
     */
    public function border(BorderStyle|string $style, Color|string|null $color = null): self
    {
        return $this->withBorder(
            $style instanceof BorderStyle ? $style : $this->toBorderStyle($style),
            $color === null ? null : $this->toColor($color),
        );
    }

    /**
     * Returns a copy with the bold flag set.
     *
     * @param bool $bold
     *
     * @return self
     */
    public function withBold(bool $bold = true): self
    {
        return $this->copy(bold: $bold);
    }

    /**
     * Returns a copy with the italic flag set.
     *
     * @param bool $italic
     *
     * @return self
     */
    public function withItalic(bool $italic = true): self
    {
        return $this->copy(italic: $italic);
    }

    /**
     * Returns a copy with the underline flag set.
     *
     * @param bool $underline
     *
     * @return self
     */
    public function withUnderline(bool $underline = true): self
    {
        return $this->copy(underline: $underline);
    }

    /**
     * Returns a copy with the font color set, or cleared when null.
     *
     * @param ?Color $fontColor
     *
     * @return self
     */
    public function withFontColor(?Color $fontColor): self
    {
        return $this->copy(fontColor: $fontColor, clearFontColor: true);
    }

    /**
     * Returns a copy with the background fill color set, or cleared when null.
     *
     * @param ?Color $backgroundColor
     *
     * @return self
     */
    public function withBackgroundColor(?Color $backgroundColor): self
    {
        return $this->copy(backgroundColor: $backgroundColor, clearBackgroundColor: true);
    }

    /**
     * Returns a copy with the number format code set, or cleared when null.
     *
     * @param ?string $numberFormat
     *
     * @return self
     */
    public function withNumberFormat(?string $numberFormat): self
    {
        return $this->copy(numberFormat: $numberFormat, clearNumberFormat: true);
    }

    /**
     * Returns a copy with the font size set, or cleared when null.
     *
     * @param ?float $fontSize
     *
     * @return self
     */
    public function withFontSize(?float $fontSize): self
    {
        return $this->copy(fontSize: $fontSize, clearFontSize: true);
    }

    /**
     * Returns a copy with the font family name set, or cleared when null.
     *
     * @param ?string $fontName
     *
     * @return self
     */
    public function withFontName(?string $fontName): self
    {
        return $this->copy(fontName: $fontName, clearFontName: true);
    }

    /**
     * Returns a copy with the horizontal alignment set, or cleared when null.
     *
     * @param ?HorizontalAlign $horizontalAlign
     *
     * @return self
     */
    public function withHorizontalAlign(?HorizontalAlign $horizontalAlign): self
    {
        return $this->copy(horizontalAlign: $horizontalAlign, clearHorizontalAlign: true);
    }

    /**
     * Returns a copy with the vertical alignment set, or cleared when null.
     *
     * @param ?VerticalAlign $verticalAlign
     *
     * @return self
     */
    public function withVerticalAlign(?VerticalAlign $verticalAlign): self
    {
        return $this->copy(verticalAlign: $verticalAlign, clearVerticalAlign: true);
    }

    /**
     * Returns a copy with the text-wrap flag set.
     *
     * @param bool $wrapText
     *
     * @return self
     */
    public function withWrapText(bool $wrapText = true): self
    {
        return $this->copy(wrapText: $wrapText);
    }

    /**
     * Returns a copy with the border set replaced, or cleared when null.
     *
     * @param ?Borders $borders
     *
     * @return self
     */
    public function withBorders(?Borders $borders): self
    {
        return $this->copy(borders: $borders, clearBorders: true);
    }

    /**
     * Convenience: the same border on all four edges.
     *
     * @param BorderStyle $style
     * @param ?Color $color
     *
     * @return Style
     */
    public function withBorder(BorderStyle $style, ?Color $color = null): self
    {
        return $this->copy(borders: Borders::all($style, $color), clearBorders: true);
    }

    /**
     * True when no formatting is applied (the default style).
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return !$this->bold
            && !$this->italic
            && !$this->underline
            && $this->fontColor === null
            && $this->backgroundColor === null
            && $this->numberFormat === null
            && $this->fontSize === null
            && $this->fontName === null
            && $this->horizontalAlign === null
            && $this->verticalAlign === null
            && !$this->wrapText
            && $this->borders === null;
    }

    /**
     * Coerces a Color or hex string into a Color.
     *
     * @param Color|string $color
     *
     * @return Color
     */
    private function toColor(Color|string $color): Color
    {
        return $color instanceof Color ? $color : Color::hex($color);
    }

    /**
     * Resolves a horizontal-alignment keyword into a HorizontalAlign, or throws.
     *
     * @param string $align
     *
     * @return HorizontalAlign
     */
    private function toHorizontalAlign(string $align): HorizontalAlign
    {
        return HorizontalAlign::tryFrom($align)
            ?? throw new InvalidArgumentException(
                sprintf('Unknown alignment "%s". Use left, center, right, fill, or justify.', $align),
            );
    }

    /**
     * Resolves a vertical-alignment keyword into a VerticalAlign, or throws.
     *
     * @param string $align
     *
     * @return VerticalAlign
     */
    private function toVerticalAlign(string $align): VerticalAlign
    {
        return VerticalAlign::tryFrom($align === 'middle' ? 'center' : $align)
            ?? throw new InvalidArgumentException(
                sprintf('Unknown vertical alignment "%s". Use top, middle, or bottom.', $align),
            );
    }

    /**
     * Resolves a border-style keyword into a BorderStyle, or throws.
     *
     * @param string $style
     *
     * @return BorderStyle
     */
    private function toBorderStyle(string $style): BorderStyle
    {
        return BorderStyle::tryFrom($style)
            ?? throw new InvalidArgumentException(
                sprintf('Unknown border style "%s". Use thin, medium, thick, dashed, dotted, or double.', $style),
            );
    }

    /**
     * Reconstruct, overriding the named fields. Nullable fields take an explicit
     * `clear*` flag so passing `null` can actually clear them (vs. "not provided").
     *
     * @param bool $clearFontColor
     * @param bool $clearBackgroundColor
     * @param bool $clearNumberFormat
     * @param bool $clearFontSize
     * @param bool $clearFontName
     * @param bool $clearHorizontalAlign
     * @param bool $clearVerticalAlign
     * @param bool $clearBorders
     * @param ?bool $bold
     * @param ?bool $italic
     * @param ?bool $underline
     * @param ?Color $fontColor
     * @param ?Color $backgroundColor
     * @param ?string $numberFormat
     * @param ?float $fontSize
     * @param ?string $fontName
     * @param ?HorizontalAlign $horizontalAlign
     * @param ?VerticalAlign $verticalAlign
     * @param ?bool $wrapText
     * @param ?Borders $borders
     *
     * @return Style
     */
    private function copy(
        ?bool $bold = null,
        ?bool $italic = null,
        ?bool $underline = null,
        ?Color $fontColor = null,
        bool $clearFontColor = false,
        ?Color $backgroundColor = null,
        bool $clearBackgroundColor = false,
        ?string $numberFormat = null,
        bool $clearNumberFormat = false,
        ?float $fontSize = null,
        bool $clearFontSize = false,
        ?string $fontName = null,
        bool $clearFontName = false,
        ?HorizontalAlign $horizontalAlign = null,
        bool $clearHorizontalAlign = false,
        ?VerticalAlign $verticalAlign = null,
        bool $clearVerticalAlign = false,
        ?bool $wrapText = null,
        ?Borders $borders = null,
        bool $clearBorders = false,
    ): self {
        return new self(
            bold: $bold ?? $this->bold,
            italic: $italic ?? $this->italic,
            underline: $underline ?? $this->underline,
            fontColor: $clearFontColor ? $fontColor : $this->fontColor,
            backgroundColor: $clearBackgroundColor ? $backgroundColor : $this->backgroundColor,
            numberFormat: $clearNumberFormat ? $numberFormat : $this->numberFormat,
            fontSize: $clearFontSize ? $fontSize : $this->fontSize,
            fontName: $clearFontName ? $fontName : $this->fontName,
            horizontalAlign: $clearHorizontalAlign ? $horizontalAlign : $this->horizontalAlign,
            verticalAlign: $clearVerticalAlign ? $verticalAlign : $this->verticalAlign,
            wrapText: $wrapText ?? $this->wrapText,
            borders: $clearBorders ? $borders : $this->borders,
        );
    }
}
