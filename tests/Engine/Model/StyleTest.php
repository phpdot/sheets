<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Tests\Engine\Model;

use PHPdot\Sheets\Engine\Model\Color;
use PHPdot\Sheets\Engine\Model\Style;
use PHPUnit\Framework\TestCase;

final class StyleTest extends TestCase
{
    public function testDefaultIsEmpty(): void
    {
        self::assertTrue((new Style())->isEmpty());
    }

    public function testWithBoldIsImmutable(): void
    {
        $base = new Style();
        $bold = $base->withBold();

        self::assertFalse($base->bold);
        self::assertTrue($bold->bold);
        self::assertFalse($bold->isEmpty());
    }

    public function testWithFontColorPreservesOtherFields(): void
    {
        $styled = (new Style(bold: true))->withFontColor(Color::hex('#112233'));

        self::assertTrue($styled->bold);
        self::assertNotNull($styled->fontColor);
        self::assertSame('112233', $styled->fontColor->rgb);
    }
}
