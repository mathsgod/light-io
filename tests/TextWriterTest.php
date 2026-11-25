<?php

declare(strict_types=1);

namespace Light\IO\Tests;

use InvalidArgumentException;
use Light\IO\StreamWriter;
use Light\IO\StringWriter;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class TextWriterTest extends TestCase
{
    public function testStringWriterAccumulatesWritesInOrder(): void
    {
        $writer = new StringWriter();
        self::assertSame('', $writer->toString());

        $writer->write('first');
        $writer->write('');
        $writer->write("\nsecond\0\xFF");

        self::assertSame("first\nsecond\0\xFF", $writer->toString());
    }

    public function testStreamWriterWritesExactBytesAndLeavesStreamOpen(): void
    {
        $stream = fopen('php://temp', 'w+b');
        $text = str_repeat("字\0\xFF", 4096);

        try {
            $writer = new StreamWriter($stream);
            $writer->write('first');
            $writer->write('');
            $writer->write($text);
            unset($writer);

            self::assertTrue(rewind($stream));
            self::assertSame('first' . $text, stream_get_contents($stream));
        } finally {
            fclose($stream);
        }
    }

    public function testStreamWriterRejectsInvalidResource(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new StreamWriter('php://temp');
    }

    public function testStreamWriterRejectsClosedResource(): void
    {
        $stream = fopen('php://temp', 'w+b');
        fclose($stream);

        $this->expectException(InvalidArgumentException::class);
        new StreamWriter($stream);
    }

    public function testStreamWriterRejectsReadOnlyStream(): void
    {
        $stream = fopen('php://temp', 'rb');

        try {
            $this->expectException(InvalidArgumentException::class);
            new StreamWriter($stream);
        } finally {
            fclose($stream);
        }
    }

    public function testStreamWriterThrowsIfCallerClosesStreamBeforeWriting(): void
    {
        $stream = fopen('php://temp', 'w+b');
        $writer = new StreamWriter($stream);
        fclose($stream);

        $this->expectException(RuntimeException::class);
        $writer->write('text');
    }

    public function testStreamWriterRetriesPartialWriteAndThrowsWhenItStalls(): void
    {
        $protocol = 'lightiopartial';
        self::assertTrue(stream_wrapper_register($protocol, PartialWriteStream::class));

        try {
            $stream = fopen($protocol . '://test', 'wb');
            try {
                $wrapper = stream_get_meta_data($stream)['wrapper_data'];
                $writer = new StreamWriter($stream);
                $writer->write('abcdef');
                self::assertSame('abcdef', $wrapper->contents);

                $wrapper->stalled = true;
                $this->expectException(RuntimeException::class);
                $writer->write('more');
            } finally {
                fclose($stream);
            }
        } finally {
            stream_wrapper_unregister($protocol);
        }
    }
}

final class PartialWriteStream
{
    public mixed $context;
    public string $contents = '';
    public bool $stalled = false;
    private int $calls = 0;

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        return true;
    }

    public function stream_eof(): bool
    {
        return true;
    }

    public function stream_write(string $data): int
    {
        ++$this->calls;
        if ($this->stalled || $this->calls === 2) {
            return 0;
        }

        $chunk = substr($data, 0, 3);
        $this->contents .= $chunk;

        return strlen($chunk);
    }
}
