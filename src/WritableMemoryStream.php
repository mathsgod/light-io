<?php

declare(strict_types=1);

namespace Light\IO;

use InvalidArgumentException;
use RuntimeException;

final class WritableMemoryStream extends Stream
{
    private string $buffer;
    private readonly int $capacity;
    private int $length;
    private int $position = 0;

    public function __construct(string &$buffer)
    {
        $this->buffer =& $buffer;
        $this->capacity = $this->length = strlen($buffer);
    }

    public function canRead(): bool
    {
        return isset($this->buffer);
    }

    public function canWrite(): bool
    {
        return isset($this->buffer);
    }

    public function canSeek(): bool
    {
        return isset($this->buffer);
    }

    public function read(int $count): string
    {
        if ($count < 0) {
            throw new InvalidArgumentException('Expected a non-negative byte count.');
        }

        $this->ensureOpen();
        $available = max(0, $this->length - $this->position);
        $bytes = substr($this->buffer, $this->position, min($count, $available));
        $this->position += strlen($bytes);

        return $bytes;
    }

    public function write(string $bytes): void
    {
        $this->ensureOpen();
        $count = strlen($bytes);
        if ($count === 0) {
            return;
        }

        if ($count > $this->capacity - $this->position) {
            throw new RuntimeException('The write would exceed the fixed buffer capacity.');
        }

        $start = min($this->length, $this->position);
        $replacement = str_repeat("\0", $this->position - $start) . $bytes;
        $this->buffer = substr_replace($this->buffer, $replacement, $start, strlen($replacement));
        $this->position += $count;
        $this->length = max($this->length, $this->position);
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        $this->ensureOpen();
        $base = match ($whence) {
            SEEK_SET => 0,
            SEEK_CUR => $this->position,
            SEEK_END => $this->length,
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

        return $this->length;
    }

    public function setLength(int $bytes): void
    {
        $this->ensureOpen();
        if ($bytes < 0) {
            throw new InvalidArgumentException('Expected a non-negative stream length.');
        }

        if ($bytes > $this->capacity) {
            throw new RuntimeException('The requested length would exceed the fixed buffer capacity.');
        }

        if ($bytes > $this->length) {
            $count = $bytes - $this->length;
            $this->buffer = substr_replace($this->buffer, str_repeat("\0", $count), $this->length, $count);
        }

        $this->length = $bytes;
        $this->position = min($this->position, $bytes);
    }

    public function flush(): void
    {
        $this->ensureOpen();
    }

    public function close(): void
    {
        unset($this->buffer);
    }

    private function ensureOpen(): void
    {
        if (!isset($this->buffer)) {
            throw new RuntimeException('The stream is closed.');
        }

        if (strlen($this->buffer) !== $this->capacity) {
            throw new RuntimeException('The referenced buffer has changed size.');
        }
    }
}
