<?php

declare(strict_types=1);

namespace Light\IO\Tests;

use Light\IO\BinaryReader;
use Light\IO\ReadOnlyMemoryStream;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ReadOnlyMemoryStreamTest extends TestCase
{
    public function testReadsBinaryValuesAndSeeksByByte(): void
    {
        $stream = new ReadOnlyMemoryStream("\x2A\0\0\0\x02Hi");
        $reader = new BinaryReader($stream);

        self::assertSame(42, $reader->readInt32());
        self::assertSame(4, $stream->tell());
        self::assertSame('Hi', $reader->readString());
        $stream->seek(-2, SEEK_END);
        $stream->seek(1, SEEK_CUR);
        self::assertSame('i', $stream->read(10));
        $stream->seek(1, SEEK_END);
        self::assertSame('', $stream->read(1));
        self::assertSame(8, $stream->tell());

        $reader->close();
        self::assertFalse($stream->canRead());
        self::assertFalse($stream->canSeek());
        $this->expectException(RuntimeException::class);
        $stream->read(1);
    }

    public function testRejectsWriting(): void
    {
        $stream = new ReadOnlyMemoryStream('');
        self::assertFalse($stream->canWrite());
        $this->expectException(RuntimeException::class);
        $stream->write('data');
    }
}
