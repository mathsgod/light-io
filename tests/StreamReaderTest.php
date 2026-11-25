<?php

declare(strict_types=1);

namespace Light\IO\Tests;

use InvalidArgumentException;
use Light\IO\StreamReader;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class StreamReaderTest extends TestCase
{
    public function testReadLineReturnsUnterminatedLastLineAndThenNull(): void
    {
        $stream = fopen('php://temp', 'w+b');
        $reader = new StreamReader($stream);

        try {
            fwrite($stream, "first\nlast");
            rewind($stream);

            self::assertSame('first', $reader->readLine());
            self::assertSame('last', $reader->readLine());
            self::assertNull($reader->readLine());
        } finally {
            $reader->close();
        }

        $reader->close();
        self::assertFalse(is_resource($stream));
        $this->expectException(RuntimeException::class);
        $reader->readToEnd();
    }

    public function testStreamReaderRejectsInvalidResource(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new StreamReader('php://temp');
    }

    public function testStreamReaderRejectsWriteOnlyStream(): void
    {
        $stream = fopen('php://output', 'wb');

        try {
            $this->expectException(InvalidArgumentException::class);
            new StreamReader($stream);
        } finally {
            fclose($stream);
        }
    }
}
