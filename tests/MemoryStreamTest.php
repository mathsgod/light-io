<?php

declare(strict_types=1);

namespace Light\IO\Tests;

use Light\IO\BinaryReader;
use Light\IO\BinaryWriter;
use Light\IO\MemoryStream;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class MemoryStreamTest extends TestCase
{
    public function testBinaryRoundTripFromAnEmptyStream(): void
    {
        $stream = new MemoryStream();
        $writer = new BinaryWriter($stream);
        $writer->writeInt32(42);
        $writer->writeString('Hi');
        self::assertSame(7, $stream->length());

        $stream->seek(0);
        $reader = new BinaryReader($stream);
        self::assertSame(42, $reader->readInt32());
        self::assertSame('Hi', $reader->readString());
        self::assertSame('', $stream->read(1));
        self::assertSame('2a000000024869', bin2hex($stream->toString()));

        $reader->close();
        self::assertFalse($stream->canRead());
        self::assertSame('2a000000024869', bin2hex($stream->toString()));
        $this->expectException(RuntimeException::class);
        $stream->write('x');
    }

    public function testOverwritesAndGrowsWithZeroFilledGaps(): void
    {
        $initial = 'abcde';
        $stream = new MemoryStream($initial);
        $stream->seek(1);
        $stream->write('X');
        self::assertSame('aXcde', $stream->toString());
        self::assertSame('abcde', $initial);

        $stream->seek(2, SEEK_END);
        $stream->write('Y');
        self::assertSame("aXcde\0\0Y", $stream->toString());
        $stream->setLength(3);
        self::assertSame(3, $stream->tell());
        $stream->setLength(6);
        self::assertSame(3, $stream->tell());
        self::assertSame("\0\0\0", $stream->readToEnd());
        self::assertSame("aXc\0\0\0", $stream->toString());
    }
}
