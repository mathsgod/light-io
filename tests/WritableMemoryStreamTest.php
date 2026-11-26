<?php

declare(strict_types=1);

namespace Light\IO\Tests;

use Light\IO\BinaryReader;
use Light\IO\BinaryWriter;
use Light\IO\WritableMemoryStream;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class WritableMemoryStreamTest extends TestCase
{
    public function testBinaryWriterUpdatesTheOriginalBuffer(): void
    {
        $buffer = str_repeat("\0", 6);
        $stream = new WritableMemoryStream($buffer);
        $writer = new BinaryWriter($stream);
        $stream->seek(4);
        $writer->writeBytes('Hi');
        $stream->seek(0);
        $writer->writeInt32(2);
        self::assertSame("\x02\0\0\0Hi", $buffer);

        $stream->seek(0);
        $reader = new BinaryReader($stream);
        self::assertSame(2, $reader->readInt32());
        self::assertSame('Hi', $reader->readBytes(2));
        $reader->close();
        self::assertFalse($stream->canWrite());
        self::assertSame("\x02\0\0\0Hi", $buffer);
    }

    public function testResizingAndWritingPastTheLogicalEndClearsTheGap(): void
    {
        $buffer = 'abcdef';
        $stream = new WritableMemoryStream($buffer);
        $stream->seek(5);
        $stream->setLength(2);
        self::assertSame(2, $stream->tell());
        self::assertSame('', $stream->read(1));

        $stream->seek(2, SEEK_END);
        $stream->write('X');
        self::assertSame(5, $stream->length());
        self::assertSame("ab\0\0Xf", $buffer);
        $stream->setLength(6);
        self::assertSame("ab\0\0X\0", $buffer);
    }

    public function testOverflowLeavesBufferAndPositionIntact(): void
    {
        $buffer = 'abc';
        $stream = new WritableMemoryStream($buffer);
        $stream->seek(2);

        $this->expectException(RuntimeException::class);
        try {
            $stream->write('XY');
        } finally {
            self::assertSame('abc', $buffer);
            self::assertSame(2, $stream->tell());
        }
    }
}
