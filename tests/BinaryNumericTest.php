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

    public function testHalfRoundingAndSpecialValues(): void
    {
        $stream = new MemoryStream();
        $writer = new BinaryWriter($stream);
        foreach ([-0.0, 2 ** -25, 2 ** -24, 1 + 2 ** -11, 1 + 3 * 2 ** -11, 65504.0, 65520.0, -INF] as $value) {
            $writer->writeHalf($value);
        }

        self::assertSame('008000000100003c023cff7b007c00fc', bin2hex($stream->toString()));
        $reader = new BinaryReader(new MemoryStream(hex2bin('00800100023c007c00fc007e')));
        self::assertSame(-INF, fdiv(1.0, $reader->readHalf()));
        self::assertSame(2 ** -24, $reader->readHalf());
        self::assertSame(1 + 2 ** -9, $reader->readHalf());
        self::assertSame(INF, $reader->readHalf());
        self::assertSame(-INF, $reader->readHalf());
        self::assertTrue(is_nan($reader->readHalf()));
    }

    public function testDecimalPreservesPrecisionAndScale(): void
    {
        $stream = new MemoryStream();
        $writer = new BinaryWriter($stream);
        $writer->writeDecimal('-0.10');
        $writer->writeDecimal('79228162514264337593543950335');
        $writer->writeDecimal('-0.000');
        self::assertSame('0a000000000000000000000000000280ffffffffffffffffffffffff00000000'
            . '00000000000000000000000000000380', bin2hex($stream->toString()));

        $stream->seek(0);
        $reader = new BinaryReader($stream);
        self::assertSame('-0.10', $reader->readDecimal());
        self::assertSame('79228162514264337593543950335', $reader->readDecimal());
        self::assertSame('-0.000', $reader->readDecimal());

        $this->expectException(InvalidArgumentException::class);
        $writer->writeDecimal('79228162514264337593543950336');
    }

    public function testRejectsInvalidDecimalFlags(): void
    {
        $reader = new BinaryReader(new MemoryStream(str_repeat("\0", 14) . "\x1D\0"));
        $this->expectException(RuntimeException::class);
        $reader->readDecimal();
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
