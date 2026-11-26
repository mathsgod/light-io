<?php

declare(strict_types=1);

namespace Light\IO\Tests;

use InvalidArgumentException;
use Light\IO\BinaryWriter;
use PHPUnit\Framework\TestCase;

final class BinaryWriterTest extends TestCase
{
    public function testWritesDotNetBinaryFormats(): void
    {
        $stream = fopen('php://temp', 'w+b');
        $writer = new BinaryWriter($stream);

        try {
            $writer->writeInt32(42);
            $writer->writeInt32(-2147483648);
            $writer->writeBoolean(true);
            $writer->writeBoolean(false);
            $writer->writeString('Hi');
            $writer->writeString('');
            $writer->writeString(str_repeat('é', 64));
            $writer->writeBytes("\0\xFF");

            self::assertTrue(rewind($stream));
            self::assertSame(
                '2a000000000000800100024869008001' . str_repeat('c3a9', 64) . '00ff',
                bin2hex(stream_get_contents($stream)),
            );
        } finally {
            $writer->close();
        }

        self::assertFalse(is_resource($stream));
    }

    public function testRejectsInvalidUtf8BeforeWritingThePrefix(): void
    {
        $stream = fopen('php://temp', 'w+b');
        $writer = new BinaryWriter($stream);

        try {
            $this->expectException(InvalidArgumentException::class);
            $writer->writeString("\xFF");
        } finally {
            try {
                self::assertSame(0, ftell($stream));
            } finally {
                $writer->close();
            }
        }
    }
}
