<?php

declare(strict_types=1);

namespace Light\IO\Tests;

use Light\IO\StringReader;
use PHPUnit\Framework\TestCase;

final class StringReaderTest extends TestCase
{
    public function testReadsLfAndCrlfLinesIncludingBlankAndUnterminatedLines(): void
    {
        $reader = new StringReader("first\r\n\r\nthird\nlast\r");

        self::assertSame('first', $reader->readLine());
        self::assertSame('', $reader->readLine());
        self::assertSame('third', $reader->readLine());
        self::assertSame("last\r", $reader->readLine());
        self::assertNull($reader->readLine());
    }

    public function testReadToEndReturnsRemainingBytesAndAdvancesToEof(): void
    {
        $reader = new StringReader("first\n字\0\xFF\r\n");

        self::assertSame('first', $reader->readLine());
        self::assertSame("字\0\xFF\r\n", $reader->readToEnd());
        self::assertSame('', $reader->readToEnd());
        self::assertNull($reader->readLine());
    }

    public function testEmptyInputIsAtEof(): void
    {
        $reader = new StringReader('');

        self::assertNull($reader->readLine());
        self::assertSame('', $reader->readToEnd());
    }

    public function testTrailingNewlineDoesNotCreateAnotherLine(): void
    {
        $reader = new StringReader("\n");

        self::assertSame('', $reader->readLine());
        self::assertNull($reader->readLine());
    }
}
