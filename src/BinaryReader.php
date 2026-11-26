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
        $value = unpack('V', $this->readExactly(4))[1];

        return $value > 2147483647 ? $value - 4294967296 : $value;
    }

    public function readBoolean(): bool
    {
        return $this->readExactly(1) !== "\0";
    }

    public function readString(): string
    {
        $length = 0;
        for ($shift = 0; $shift <= 28; $shift += 7) {
            $byte = ord($this->readExactly(1));
            if ($shift === 28 && $byte > 7) {
                throw new RuntimeException('Invalid 32-bit string length prefix.');
            }

            $length |= ($byte & 0x7F) << $shift;
            if ($byte < 128) {
                break;
            }
        }

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

    private function readExactly(int $count): string
    {
        $bytes = $this->readBytes($count);
        if (strlen($bytes) !== $count) {
            throw new RuntimeException('Unexpected end of stream.');
        }

        return $bytes;
    }
}
