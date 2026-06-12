<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Tests\Engine\Model;

use PHPdot\Sheets\Engine\Model\Color;
use PHPdot\Sheets\Engine\Support\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ColorTest extends TestCase
{
    public function testHexNormalizesCaseAndHash(): void
    {
        self::assertSame('FF0000', Color::hex('#ff0000')->rgb);
    }

    public function testHexExpandsShorthand(): void
    {
        self::assertSame('FF0000', Color::hex('f00')->rgb);
    }

    public function testRgbComponents(): void
    {
        self::assertSame('FF8000', Color::rgb(255, 128, 0)->rgb);
    }

    public function testRejectsInvalidHex(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Color::hex('nothex');
    }

    public function testRejectsRgbOutOfRange(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Color::rgb(256, 0, 0);
    }
}
