<?php

declare(strict_types=1);

/**
 * An immutable RGB color, normalized to 6-digit uppercase hex (no leading hash).
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Sheets\Engine\Model;

use PHPdot\Sheets\Engine\Support\InvalidArgumentException;

final class Color
{
    /**
     * Wraps a normalized 6-digit uppercase hex RGB string; construct via hex().
     *
     * @param string $rgb
     */
    private function __construct(
        public readonly string $rgb,
    ) {}

    /**
     * Create from a hex string: "#FF0000", "ff0000", or the shorthand "f00".
     *
     * @param string $hex
     *
     * @throws InvalidArgumentException When the value is not valid hex.
     *
     * @return self
     */
    public static function hex(string $hex): self
    {
        $value = strtoupper(ltrim($hex, '#'));

        if (strlen($value) === 3) {
            $value = $value[0] . $value[0] . $value[1] . $value[1] . $value[2] . $value[2];
        }

        if (preg_match('/^[0-9A-F]{6}$/', $value) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid hex color: "%s".', $hex));
        }

        return new self($value);
    }

    /**
     * Create from red, green and blue components, each in 0-255.
     *
     * @param int $red
     * @param int $green
     * @param int $blue
     *
     * @throws InvalidArgumentException When a component is out of range.
     *
     * @return Color
     */
    public static function rgb(int $red, int $green, int $blue): self
    {
        foreach (['red' => $red, 'green' => $green, 'blue' => $blue] as $name => $component) {
            if ($component < 0 || $component > 255) {
                throw new InvalidArgumentException(
                    sprintf('Color component "%s" must be 0-255, got %d.', $name, $component),
                );
            }
        }

        return new self(sprintf('%02X%02X%02X', $red, $green, $blue));
    }
}
