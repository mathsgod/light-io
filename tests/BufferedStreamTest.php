<?php

declare(strict_types=1);

namespace Light\IO\Tests;

use Light\IO\BinaryReader;
use Light\IO\BinaryWriter;
use Light\IO\BufferedStream;
use PHPUnit\Framework\TestCase;

final class BufferedStreamTest extends TestCase
{
    public function testBatchesWritesAndFlushesExplicitly(): void
    {
        $resource = fopen('php://temp', 'w+b');
        $stream = new BufferedStream($resource, 4);

        try {
            $stream->write('ab');
            self::assertSame(0, ftell($resource));
            self::assertSame(2, $stream->tell());
            $stream->write('cd');
            self::assertSame(4, ftell($resource));
            $stream->write('e');
            $stream->flush();
            self::assertSame(5, ftell($resource));
        } finally {
            $stream->close();
        }

        self::assertFalse(is_resource($resource));
    }

    public function testSwitchesBetweenReadingAndWritingAtTheLogicalPosition(): void
    {
        $resource = fopen('php://temp', 'w+b');
        fwrite($resource, 'abcdef');
        rewind($resource);
        $stream = new BufferedStream($resource, 4);

        try {
            self::assertSame('a', $stream->read(1));
            self::assertSame(4, ftell($resource));
            self::assertSame('b', $stream->read(1));
            self::assertSame(4, ftell($resource));
            self::assertSame(2, $stream->tell());
            $stream->write('X');
            self::assertSame('d', $stream->read(1));
            $stream->seek(-2, SEEK_CUR);
            self::assertSame('Xdef', $stream->readToEnd());
        } finally {
            $stream->close();
        }
    }

    public function testBinaryRoundTripAcrossBufferBoundaries(): void
    {
        $stream = new BufferedStream(fopen('php://temp', 'w+b'), 3);

        try {
            $writer = new BinaryWriter($stream);
            $writer->writeBoolean(true);
            $writer->writeInt32(-42);
            $writer->writeString('你好');
            $stream->seek(0);

            $reader = new BinaryReader($stream);
            self::assertTrue($reader->readBoolean());
            self::assertSame(-42, $reader->readInt32());
            self::assertSame('你好', $reader->readString());
        } finally {
            $stream->close();
        }
    }
}
