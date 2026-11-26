<?php

declare(strict_types=1);

namespace Light\IO\Tests;

use InvalidArgumentException;
use Light\IO\BinaryReader;
use Light\IO\BinaryWriter;
use Light\IO\MemoryStream;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class BinaryNumericTest extends TestCase
{
    public function testNumericValuesMatchDotNetBytes(): void
    {
        if (PHP_INT_SIZE < 8) {
            self::markTestSkipped('This fixture includes values requiring 64-bit PHP.');
        }

        $stream = new MemoryStream();
        $writer = new BinaryWriter($stream);
        $writer->writeByte(255);
        $writer->writeSByte(-128);
        $writer->writeInt16(-32768);
        $writer->writeUInt16(65535);
        $writer->writeUInt32(4294967295);
        $writer->writeInt64(PHP_INT_MIN);
        $writer->writeUInt64('18446744073709551615');
        $writer->writeUInt64('9223372036854775808');
        $writer->writeSingle(1.5);
        $writer->writeDouble(-2.5);
        $writer->write7BitEncodedInt(128);
        $writer->write7BitEncodedInt(-1);
        $writer->write7BitEncodedInt64(PHP_INT_MIN);

        $hex = 'ff800080ffffffffffff0000000000000080ffffffffffffffff0000000000000080'
             . '0000c03f00000000000004c08001ffffffff0f80808080808080808001';
        self::assertSame($hex, bin2hex($stream->toString()));

        $reader = new BinaryReader(new MemoryStream(hex2bin($hex)));
        self::assertSame(255, $reader->readByte());
        self::assertSame(-128, $reader->readSByte());
        self::assertSame(-32768, $reader->readInt16());
        self::assertSame(65535, $reader->readUInt16());
        self::assertSame(4294967295, $reader->readUInt32());
        self::assertSame(PHP_INT_MIN, $reader->readInt64());
        self::assertSame('18446744073709551615', $reader->readUInt64());
        self::assertSame('9223372036854775808', $reader->readUInt64());
        self::assertSame(1.5, $reader->readSingle());
        self::assertSame(-2.5, $reader->readDouble());
        self::assertSame(128, $reader->read7BitEncodedInt());
        self::assertSame(-1, $reader->read7BitEncodedInt());
        self::assertSame(PHP_INT_MIN, $reader->read7BitEncodedInt64());
    }

    public function testRejectsUInt64OverflowWithoutWriting(): void
    {
        if (PHP_INT_SIZE < 8) {
            self::markTestSkipped('Unsigned 64-bit I/O requires 64-bit PHP.');
        }

        $stream = new MemoryStream();
        $writer = new BinaryWriter($stream);
        $this->expectException(InvalidArgumentException::class);
        try {
            $writer->writeUInt64('18446744073709551616');
        } finally {
            self::assertSame('', $stream->toString());
        }
    }

    public function testRejectsAnOverflowingSevenBitInteger(): void
    {
        $reader = new BinaryReader(new MemoryStream("\x80\x80\x80\x80\x10"));
        $this->expectException(RuntimeException::class);
        $reader->read7BitEncodedInt();
    }
}
