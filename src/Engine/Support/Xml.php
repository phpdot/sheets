<?php

declare(strict_types=1);

/**
 * XML 1.0 text and attribute encoding for spreadsheet output.
 *
 * Illegal XML 1.0 characters (C0 control bytes other than TAB, LF, CR) are
 * stripped *before* entity escaping, so a stray control byte in user data can
 * never produce a corrupt, unopenable file — the most common failure mode of
 * hand-rolled XLSX writers.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Sheets\Engine\Support;

final class Xml
{
    /**
     * Characters not permitted in XML 1.0: 0x00-0x08, 0x0B, 0x0C, 0x0E-0x1F.
     */
    private const ILLEGAL_PATTERN = '/[\x00-\x08\x0B\x0C\x0E-\x1F]/';

    /**
     * Not instantiable — the class exposes only static XML-escaping helpers.
     */
    private function __construct() {}

    /**
     * Escape a value for use as XML element text content (e.g. inside `<t>`).
     *
     * @param string $value
     *
     * @return string
     */
    public static function text(string $value): string
    {
        $value = self::stripIllegal($value);

        return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $value);
    }

    /**
     * Escape a value for use inside a double-quoted XML attribute.
     *
     * @param string $value
     *
     * @return string
     */
    public static function attribute(string $value): string
    {
        $value = self::stripIllegal($value);

        return str_replace(
            ['&', '<', '>', '"', "\t", "\n", "\r"],
            ['&amp;', '&lt;', '&gt;', '&quot;', '&#9;', '&#10;', '&#13;'],
            $value,
        );
    }

    /**
     * Remove characters that are illegal in XML 1.0.
     *
     * @param string $value
     *
     * @return string
     */
    public static function stripIllegal(string $value): string
    {
        return preg_replace(self::ILLEGAL_PATTERN, '', $value) ?? $value;
    }
}
