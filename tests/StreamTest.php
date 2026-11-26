<?php

declare(strict_types=1);

namespace Light\IO\Tests;

use InvalidArgumentException;
use Light\IO\BufferedStream;
use Light\IO\MemoryStream;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class StreamTest extends TestCase
{
    public function testCopyUsesBothStreamsCurrentPositions(): void
    {
        $source = new BufferedStream(new MemoryStream('abcdef'), 3);
        $destination = new MemoryStream('123456');
        self::assertSame('a', $source->read(1));
        $destination->seek(2);

        $source->copyTo($destination, 2);

        self::assertSame('12bcdef', $destination->toString());
        self::assertSame(6, $source->tell());
        self::assertSame(7, $destination->tell());
    }

    public function testReadExactlyCollectsShortReadsAndThrowsOnTruncation(): void
    {
        $stream = new BufferedStream(new MemoryStream('abcdef'), 3);
        self::assertSame('a', $stream->read(1));
        self::assertSame('', $stream->readExactly(0));
        self::assertSame('bcde', $stream->readExactly(4));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unexpected end of stream.');
        try {
            $stream->readExactly(2);
        } finally {
            self::assertSame(6, $stream->tell());
        }
    }

    public function testCopyRejectsTheSameStream(): void
    {
        $stream = new MemoryStream('abc');
        $this->expectException(InvalidArgumentException::class);
        $stream->copyTo($stream);
    }
}
