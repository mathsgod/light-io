<?php

declare(strict_types=1);

namespace Light\IO;

use InvalidArgumentException;
use RuntimeException;

final class BinaryReader
{
    /** @var resource */
    private readonly mixed $stream;

    /** @param resource $stream */
    public function __construct(mixed $stream)
    {
        if (!is_resource($stream) || get_resource_type($stream) !== 'stream') {
            throw new InvalidArgumentException('Expected an open stream resource.');
        }

        $mode = stream_get_meta_data($stream)['mode'];
        if (!str_starts_with($mode, 'r') && !str_contains($mode, '+')) {
            throw new InvalidArgumentException('Expected a readable stream.');
        }

        $this->stream = $stream;
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

        if (!is_resource($this->stream)) {
            throw new RuntimeException('The stream is closed.');
        }

        $bytes = '';
        while ($count > 0) {
            $chunk = @fread($this->stream, min($count, 8192));
            if ($chunk === '' && feof($this->stream)) {
                break;
            }

            if ($chunk === false || $chunk === '') {
                throw new RuntimeException('Could not finish reading from the stream.');
            }

            $bytes .= $chunk;
            $count -= strlen($chunk);
        }

        return $bytes;
    }

    public function close(): void
    {
        if (is_resource($this->stream) && !@fclose($this->stream)) {
            throw new RuntimeException('Could not close the stream.');
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
