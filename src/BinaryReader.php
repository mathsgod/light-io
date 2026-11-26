<?php

declare(strict_types=1);

namespace Light\IO;

use InvalidArgumentException;
use RuntimeException;

final class BinaryReader
{
    private readonly Stream $stream;

    /** @param Stream|resource $stream */
    public function __construct(mixed $stream)
    {
        $this->stream = $stream instanceof Stream ? $stream : Stream::fromResource($stream);
        if (!$this->stream->canRead()) {
            throw new InvalidArgumentException('Expected a readable stream.');
        }
    }

    public function readInt32(): int
    {
        return self::signedInt32(unpack('V', $this->readExactly(4))[1]);
    }

    public function readByte(): int
    {
        return ord($this->readExactly(1));
    }

    public function readSByte(): int
    {
        return unpack('c', $this->readExactly(1))[1];
    }

    public function readInt16(): int
    {
        $value = $this->readUInt16();

        return $value > 32767 ? $value - 65536 : $value;
    }

    public function readUInt16(): int
    {
        return unpack('v', $this->readExactly(2))[1];
    }

    public function readUInt32(): int
    {
        self::require64Bit();

        return unpack('V', $this->readExactly(4))[1];
    }

    public function readInt64(): int
    {
        self::require64Bit();

        return unpack('P', $this->readExactly(8))[1];
    }

    public function readUInt64(): string
    {
        return sprintf('%u', $this->readInt64());
    }

    public function readSingle(): float
    {
        return unpack('g', $this->readExactly(4))[1];
    }

    public function readDouble(): float
    {
        return unpack('e', $this->readExactly(8))[1];
    }

    public function read7BitEncodedInt(): int
    {
        return self::signedInt32($this->read7BitInteger(28, 15, 'Invalid 32-bit 7-bit encoded integer.'));
    }

    public function read7BitEncodedInt64(): int
    {
        self::require64Bit();

        return $this->read7BitInteger(63, 1, 'Invalid 64-bit 7-bit encoded integer.');
    }

    public function readBoolean(): bool
    {
        return $this->readExactly(1) !== "\0";
    }

    public function readString(): string
    {
        $length = $this->read7BitInteger(28, 7, 'Invalid 32-bit string length prefix.');
        $value = $this->readExactly($length);
        if (preg_match('//u', $value) !== 1) {
            throw new RuntimeException('The stream contains an invalid UTF-8 string.');
        }

        return $value;
    }

    public function readBytes(int $count): string
    {
        if ($count < 0) {
            throw new InvalidArgumentException('Expected a non-negative byte count.');
        }

        if ($count === 0) {
            return $this->stream->read(0);
        }

        $bytes = '';
        while ($count > 0) {
            $chunk = $this->stream->read(min($count, 8192));
            if ($chunk === '') {
                break;
            }

            $bytes .= $chunk;
            $count -= strlen($chunk);
        }

        return $bytes;
    }

    public function close(): void
    {
        $this->stream->close();
    }

    private static function signedInt32(int $value): int
    {
        return $value > 2147483647 ? $value - 4294967296 : $value;
    }

    private function read7BitInteger(int $lastShift, int $lastByteMax, string $error): int
    {
        $value = 0;
        for ($shift = 0; $shift < $lastShift; $shift += 7) {
            $byte = $this->readByte();
            $value |= ($byte & 0x7F) << $shift;
            if ($byte < 128) {
                return $value;
            }
        }

        $byte = $this->readByte();
        if ($byte > $lastByteMax) {
            throw new RuntimeException($error);
        }

        return $value | ($byte << $lastShift);
    }

    private static function require64Bit(): void
    {
        if (PHP_INT_SIZE < 8) {
            throw new RuntimeException('This binary operation requires 64-bit PHP.');
        }
    }

    private function readExactly(int $count): string
    {
        $bytes = $this->readBytes($count);
        if (strlen($bytes) !== $count) {
            throw new RuntimeException('Unexpected end of stream.');
        }

        return $bytes;
    }
}
