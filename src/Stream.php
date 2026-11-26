<?php

declare(strict_types=1);

namespace Light\IO;

use InvalidArgumentException;
use RuntimeException;

abstract class Stream
{
    /** @param resource $resource */
    public static function fromResource(mixed $resource): self
    {
        return new ResourceStream($resource);
    }

    abstract public function canRead(): bool;

    abstract public function canWrite(): bool;

    abstract public function canSeek(): bool;

    /** Returns up to $count bytes, or an empty string at end of stream. */
    abstract public function read(int $count): string;

    /** Writes all bytes or throws. */
    abstract public function write(string $bytes): void;

    abstract public function seek(int $offset, int $whence = SEEK_SET): void;

    abstract public function tell(): int;

    abstract public function flush(): void;

    abstract public function close(): void;

    public function copyTo(Stream $destination, int $bufferSize = 8192): void
    {
        if ($bufferSize < 1) {
            throw new InvalidArgumentException('Expected a positive buffer size.');
        }

        if ($destination === $this) {
            throw new InvalidArgumentException('Expected a different destination stream.');
        }

        if (!$this->canRead() || !$destination->canWrite()) {
            throw new RuntimeException('Expected a readable source and a writable destination.');
        }

        while (($bytes = $this->read($bufferSize)) !== '') {
            $destination->write($bytes);
        }
    }

    public function readExactly(int $count): string
    {
        if ($count < 0) {
            throw new InvalidArgumentException('Expected a non-negative byte count.');
        }

        if ($count === 0) {
            return $this->read(0);
        }

        $bytes = '';
        while ($count > 0) {
            $chunk = $this->read(min($count, 8192));
            if ($chunk === '') {
                throw new RuntimeException('Unexpected end of stream.');
            }

            $bytes .= $chunk;
            $count -= strlen($chunk);
        }

        return $bytes;
    }

    /** Returns a line including its terminator, or null at end of stream. */
    public function readLine(): ?string
    {
        $line = '';
        while (($byte = $this->read(1)) !== '') {
            $line .= $byte;
            if ($byte === "\n") {
                return $line;
            }
        }

        return $line === '' ? null : $line;
    }

    public function readToEnd(): string
    {
        $bytes = '';
        while (($chunk = $this->read(8192)) !== '') {
            $bytes .= $chunk;
        }

        return $bytes;
    }
}
