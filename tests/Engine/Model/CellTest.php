<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Tests\Engine\Model;

use PHPdot\Sheets\Engine\Model\Cell;
use PHPdot\Sheets\Engine\Model\CellType;
use PHPUnit\Framework\TestCase;

final class CellTest extends TestCase
{
    public function testHoldsValueTypeAndStyle(): void
    {
        $cell = new Cell(42, CellType::Number, 3);

        self::assertSame(42, $cell->value);
        self::assertSame(CellType::Number, $cell->type);
        self::assertSame(3, $cell->styleId);
    }

    public function testWithStyleIdDoesNotMutateOriginal(): void
    {
        $original = new Cell('x', CellType::String);
        $styled = $original->withStyleId(7);

        self::assertNull($original->styleId);
        self::assertSame(7, $styled->styleId);
        self::assertNotSame($original, $styled);
        self::assertSame('x', $styled->value);
    }

    public function testWithValuePreservesStyleAndDoesNotMutate(): void
    {
        $original = new Cell('a', CellType::String, 2);
        $changed = $original->withValue(9.5, CellType::Number);

        self::assertSame('a', $original->value);
        self::assertSame(9.5, $changed->value);
        self::assertSame(CellType::Number, $changed->type);
        self::assertSame(2, $changed->styleId);
    }
}
