<?php

declare(strict_types=1);

namespace Light\IO\Tests;

use Light\IO\BinaryReader;
use Light\IO\BinaryWriter;
use Light\IO\BufferedStream;
use Light\IO\MemoryStream;
use Light\IO\StreamReader;
use Light\IO\StreamWriter;
use Light\IO\TextEncoding;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class TextEncodingTest extends TestCase
{
    public function testUtf8BomDetectionAndCharacterReads(): void
    {
        $reader = new StreamReader(new BufferedStream(new MemoryStream("\xEF\xBB\xBF😀字\r\nend"), 2), detectBom: true);
        self::assertSame('😀字', $reader->readChars(2));
        self::assertSame('', $reader->readLine());
        self::assertSame('end', $reader->readToEnd());
        self::assertNull($reader->readChar());
        self::assertSame('abc', (new StreamReader(new MemoryStream('abc'), detectBom: true))->readLine());
    }

    public function testUtf32BomTakesPrecedenceOverUtf16(): void
    {
        if (!extension_loaded('iconv')) {
            self::markTestSkipped('UTF-32 requires iconv.');
        }

        $reader = new StreamReader(new MemoryStream(hex2bin('fffe000000f60100')), TextEncoding::Utf8, detectBom: true);
        self::assertSame('😀', $reader->readChar());
        self::assertNull($reader->readChar());
    }

    public function testWritersUseSelectedEncodingAndBinaryLengthCountsBytes(): void
    {
        if (!extension_loaded('iconv')) {
            self::markTestSkipped('UTF-16 requires iconv.');
        }

        $stream = new MemoryStream();
        $writer = new StreamWriter($stream, TextEncoding::Utf16BE);
        $writer->writeChar('😀');
        self::assertSame('d83dde00', bin2hex($stream->toString()));

        $stream->seek(0);
        $binary = new BinaryWriter($stream, TextEncoding::Utf16LE);
        $binary->writeString('😀');
        $binary->writeChar('字');
        $binary->writeByte(42);
        self::assertSame('043dd800de575b2a', bin2hex($stream->toString()));

        $stream->seek(0);
        $reader = new BinaryReader($stream, TextEncoding::Utf16LE);
        self::assertSame('😀', $reader->readString());
        self::assertSame('字', $reader->readChar());
        self::assertSame(42, $reader->readByte());
    }

    public function testTruncatedCharacterThrows(): void
    {
        $reader = new StreamReader(new MemoryStream("\xF0\x9F"));
        $this->expectException(RuntimeException::class);
        $reader->readChar();
    }
}
