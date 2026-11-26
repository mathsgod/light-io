<?php

declare(strict_types=1);

namespace Light\IO;

use InvalidArgumentException;

final class BinaryWriter
{
    private readonly StreamWriter $writer;

    /** @param Stream|resource $stream */
    public function __construct(mixed $stream)
    {
        $this->writer = new StreamWriter($stream);
    }

    public function writeInt32(int $value): void
    {
        if ($value < -2147483648 || $value > 2147483647) {
            throw new InvalidArgumentException('Expected a signed 32-bit integer.');
        }

        $this->writeBytes(pack('V', $value));
    }

    public function writeBoolean(bool $value): void
    {
        $this->writeBytes($value ? "\x01" : "\x00");
    }

    public function writeString(string $value): void
    {
        $length = strlen($value);
        if ($length > 2147483647) {
            throw new InvalidArgumentException('The string is too long for a 32-bit length prefix.');
        }

        if (preg_match('//u', $value) !== 1) {
            throw new InvalidArgumentException('Expected a valid UTF-8 string.');
        }

        $prefix = '';
        while ($length >= 128) {
            $prefix .= chr(($length & 0x7F) | 0x80);
            $length >>= 7;
        }

        $this->writeBytes($prefix . chr($length));
        $this->writeBytes($value);
    }

    public function writeBytes(string $bytes): void
    {
        $this->writer->write($bytes);
    }

    public function close(): void
    {
        $this->writer->close();
    }
}
