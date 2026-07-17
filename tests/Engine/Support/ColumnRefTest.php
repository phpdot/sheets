<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Tests\Engine\Support;

use PHPdot\Sheets\Engine\Support\ColumnRef;
use PHPdot\Sheets\Engine\Support\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ColumnRefTest extends TestCase
{
    public function testLetters(): void
    {
        self::assertSame('A', ColumnRef::letters(1));
        self::assertSame('Z', ColumnRef::letters(26));
        self::assertSame('AA', ColumnRef::letters(27));
        self::assertSame('ZZ', ColumnRef::letters(702));
        self::assertSame('AAA', ColumnRef::letters(703));
        self::assertSame('XFD', ColumnRef::letters(ColumnRef::MAX));
    }

    public function testNumberRoundTrips(): void
    {
        foreach ([1, 26, 27, 702, 703, ColumnRef::MAX] as $n) {
            self::assertSame($n, ColumnRef::number(ColumnRef::letters($n)));
        }
    }

    public function testLettersRejectsAboveMax(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ColumnRef::letters(ColumnRef::MAX + 1);
    }

    public function testLettersRejectsBelowOne(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ColumnRef::letters(0);
    }

    public function testNumberRejectsNonLetters(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ColumnRef::number('A1');
    }

    public function testNumberRejectsAboveMax(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ColumnRef::number('XFE');
    }
}
