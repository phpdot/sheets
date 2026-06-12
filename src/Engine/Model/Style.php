<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Engine\Model;

use PHPdot\Sheets\Engine\Support\InvalidArgumentException;

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
final class Style
{
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
     */
    public static function make(): self
    {
        return new self();
    }

    public function bold(bool $bold = true): self
    {
        return $this->withBold($bold);
    }

    public function italic(bool $italic = true): self
    {
        return $this->withItalic($italic);
    }

    public function underline(bool $underline = true): self
    {
        return $this->withUnderline($underline);
    }

    public function fontSize(float $points): self
    {
        return $this->withFontSize($points);
    }

    public function fontName(string $name): self
    {
        return $this->withFontName($name);
    }

    /**
     * Font color as a {@see Color} or a hex string ("FF0000", "#FF0000", "f00").
     */
    public function fontColor(Color|string $color): self
    {
        return $this->withFontColor($this->toColor($color));
    }

    /**
     * Background fill as a {@see Color} or a hex string.
     */
    public function background(Color|string $color): self
    {
        return $this->withBackgroundColor($this->toColor($color));
    }

    public function numberFormat(string $code): self
    {
        return $this->withNumberFormat(NumberFormats::resolve($code));
    }

    /**
     * Horizontal alignment: a {@see HorizontalAlign} or one of
     * "left", "center", "right", "fill", "justify".
     */
    public function align(HorizontalAlign|string $align): self
    {
        return $this->withHorizontalAlign(
            $align instanceof HorizontalAlign ? $align : $this->toHorizontalAlign($align),
        );
    }

    /**
     * Vertical alignment: a {@see VerticalAlign} or one of "top", "middle"/"center", "bottom".
     */
    public function valign(VerticalAlign|string $align): self
    {
        return $this->withVerticalAlign(
            $align instanceof VerticalAlign ? $align : $this->toVerticalAlign($align),
        );
    }

    public function wrap(bool $wrap = true): self
    {
        return $this->withWrapText($wrap);
    }

    /**
     * One border on all four edges: a {@see BorderStyle} or one of
     * "thin", "medium", "thick", "dashed", "dotted", "double", with an optional color.
     */
    public function border(BorderStyle|string $style, Color|string|null $color = null): self
    {
        return $this->withBorder(
            $style instanceof BorderStyle ? $style : $this->toBorderStyle($style),
            $color === null ? null : $this->toColor($color),
        );
    }

    public function withBold(bool $bold = true): self
    {
        return $this->copy(bold: $bold);
    }

    public function withItalic(bool $italic = true): self
    {
        return $this->copy(italic: $italic);
    }

    public function withUnderline(bool $underline = true): self
    {
        return $this->copy(underline: $underline);
    }

    public function withFontColor(?Color $fontColor): self
    {
        return $this->copy(fontColor: $fontColor, clearFontColor: true);
    }

    public function withBackgroundColor(?Color $backgroundColor): self
    {
        return $this->copy(backgroundColor: $backgroundColor, clearBackgroundColor: true);
    }

    public function withNumberFormat(?string $numberFormat): self
    {
        return $this->copy(numberFormat: $numberFormat, clearNumberFormat: true);
    }

    public function withFontSize(?float $fontSize): self
    {
        return $this->copy(fontSize: $fontSize, clearFontSize: true);
    }

    public function withFontName(?string $fontName): self
    {
        return $this->copy(fontName: $fontName, clearFontName: true);
    }

    public function withHorizontalAlign(?HorizontalAlign $horizontalAlign): self
    {
        return $this->copy(horizontalAlign: $horizontalAlign, clearHorizontalAlign: true);
    }

    public function withVerticalAlign(?VerticalAlign $verticalAlign): self
    {
        return $this->copy(verticalAlign: $verticalAlign, clearVerticalAlign: true);
    }

    public function withWrapText(bool $wrapText = true): self
    {
        return $this->copy(wrapText: $wrapText);
    }

    public function withBorders(?Borders $borders): self
    {
        return $this->copy(borders: $borders, clearBorders: true);
    }

    /**
     * Convenience: the same border on all four edges.
     */
    public function withBorder(BorderStyle $style, ?Color $color = null): self
    {
        return $this->copy(borders: Borders::all($style, $color), clearBorders: true);
    }

    /**
     * True when no formatting is applied (the default style).
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

    private function toColor(Color|string $color): Color
    {
        return $color instanceof Color ? $color : Color::hex($color);
    }

    private function toHorizontalAlign(string $align): HorizontalAlign
    {
        return HorizontalAlign::tryFrom($align)
            ?? throw new InvalidArgumentException(
                sprintf('Unknown alignment "%s". Use left, center, right, fill, or justify.', $align),
            );
    }

    private function toVerticalAlign(string $align): VerticalAlign
    {
        return VerticalAlign::tryFrom($align === 'middle' ? 'center' : $align)
            ?? throw new InvalidArgumentException(
                sprintf('Unknown vertical alignment "%s". Use top, middle, or bottom.', $align),
            );
    }

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
