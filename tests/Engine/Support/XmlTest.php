<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Tests\Engine\Support;

use PHPdot\Sheets\Engine\Support\Xml;
use PHPUnit\Framework\TestCase;

final class XmlTest extends TestCase
{
    public function testTextEscapesEntities(): void
    {
        self::assertSame('a &amp; b &lt;c&gt;', Xml::text('a & b <c>'));
    }

    public function testTextStripsIllegalControlCharacters(): void
    {
        self::assertSame('ABCD', Xml::text("AB\x01\x1FCD"));
    }

    public function testTextPreservesAllowedWhitespace(): void
    {
        self::assertSame("a\tb\nc\rd", Xml::text("a\tb\nc\rd"));
    }

    public function testAttributeEscapesQuotesAndWhitespace(): void
    {
        self::assertSame('say &quot;hi&quot;&#9;ok', Xml::attribute("say \"hi\"\tok"));
    }

    public function testStripIllegalLeavesCleanStringUntouched(): void
    {
        self::assertSame('clean text 123', Xml::stripIllegal('clean text 123'));
    }
}
