<?php

declare(strict_types=1);

namespace Light\IO;

use InvalidArgumentException;
use RuntimeException;

final class MemoryStream extends Stream
{
    private int $position = 0;
    private bool $closed = false;

    public function __construct(private string $bytes = '')
    {
    }

    public function canRead(): bool
    {
        return !$this->closed;
    }

    public function canWrite(): bool
    {
        return !$this->closed;
    }

    public function canSeek(): bool
    {
        return !$this->closed;
    }

    public function read(int $count): string
    {
        if ($count < 0) {
            throw new InvalidArgumentException('Expected a non-negative byte count.');
        }

        $this->ensureOpen();
        $bytes = substr($this->bytes, $this->position, $count);
        $this->position += strlen($bytes);

        return $bytes;
    }

    public function write(string $bytes): void
    {
        $this->ensureOpen();
        if ($bytes === '') {
            return;
        }

        $count = strlen($bytes);
        if ($count > PHP_INT_MAX - $this->position) {
            throw new RuntimeException('The requested stream length is out of range.');
        }

        $length = strlen($this->bytes);
        if ($this->position < $length) {
            $this->bytes = substr_replace($this->bytes, $bytes, $this->position, $count);
        } else {
            $this->bytes .= str_repeat("\0", $this->position - $length) . $bytes;
        }

        $this->position += $count;
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        $this->ensureOpen();
        $base = match ($whence) {
            SEEK_SET => 0,
            SEEK_CUR => $this->position,
            SEEK_END => strlen($this->bytes),
            default => throw new InvalidArgumentException('Invalid seek origin.'),
        };

        if ($offset < -$base || $offset > PHP_INT_MAX - $base) {
            throw new RuntimeException('The requested stream position is out of range.');
        }

        $this->position = $base + $offset;
    }

    public function tell(): int
    {
        $this->ensureOpen();

        return $this->position;
    }

    public function length(): int
    {
        $this->ensureOpen();

        return strlen($this->bytes);
    }

    public function setLength(int $bytes): void
    {
        $this->ensureOpen();
        if ($bytes < 0) {
            throw new InvalidArgumentException('Expected a non-negative stream length.');
        }

        $length = strlen($this->bytes);
        if ($bytes < $length) {
            $this->bytes = substr($this->bytes, 0, $bytes);
        } elseif ($bytes > $length) {
            $this->bytes .= str_repeat("\0", $bytes - $length);
        }

        $this->position = min($this->position, $bytes);
    }

    public function toString(): string
    {
        return $this->bytes;
    }

    public function flush(): void
    {
        $this->ensureOpen();
    }

    public function close(): void
    {
        $this->closed = true;
    }

    private function ensureOpen(): void
    {
        if ($this->closed) {
            throw new RuntimeException('The stream is closed.');
        }
    }
}
