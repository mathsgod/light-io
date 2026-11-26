<?php

declare(strict_types=1);

namespace Light\IO\Tests;

use Light\IO\BinaryReader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class BinaryReaderTest extends TestCase
{
    public function testReadsDotNetBinaryFormats(): void
    {
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, hex2bin('2a000000000000800100024869008001' . str_repeat('c3a9', 64) . '00ff'));
        rewind($stream);
        $reader = new BinaryReader($stream);

        try {
            self::assertSame(42, $reader->readInt32());
            self::assertSame(-2147483648, $reader->readInt32());
            self::assertTrue($reader->readBoolean());
            self::assertFalse($reader->readBoolean());
            self::assertSame('Hi', $reader->readString());
            self::assertSame('', $reader->readString());
            self::assertSame(str_repeat('é', 64), $reader->readString());
            self::assertSame("\0\xFF", $reader->readBytes(10));
            self::assertSame('', $reader->readBytes(1));
        } finally {
            $reader->close();
        }

        self::assertFalse(is_resource($stream));
    }

    #[DataProvider('invalidStrings')]
    public function testRejectsInvalidStringData(string $bytes, string $message): void
    {
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, $bytes);
        rewind($stream);
        $reader = new BinaryReader($stream);

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage($message);
            $reader->readString();
        } finally {
            $reader->close();
        }
    }

    public static function invalidStrings(): iterable
    {
        yield 'truncated prefix' => ["\x80", 'Unexpected end of stream.'];
        yield 'truncated payload' => ["\x02H", 'Unexpected end of stream.'];
        yield 'overflowing prefix' => ["\x80\x80\x80\x80\x08", 'Invalid 32-bit string length prefix.'];
        yield 'invalid UTF-8' => ["\x01\xFF", 'The stream contains an invalid UTF-8 string.'];
    }
}
