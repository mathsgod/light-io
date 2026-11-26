<?php

declare(strict_types=1);

namespace Light\IO;

use InvalidArgumentException;
use RuntimeException;

final class ReadOnlyMemoryStream extends Stream
{
    private ?string $bytes;
    private int $position = 0;

    public function __construct(string $bytes)
    {
        $this->bytes = $bytes;
    }

    public function canRead(): bool
    {
        return $this->bytes !== null;
    }

    public function canWrite(): bool
    {
        return false;
    }

    public function canSeek(): bool
    {
        return $this->bytes !== null;
    }

    public function read(int $count): string
    {
        if ($count < 0) {
            throw new InvalidArgumentException('Expected a non-negative byte count.');
        }

        $bytes = $this->getBytes();
        $chunk = substr($bytes, $this->position, $count);
        $this->position += strlen($chunk);

        return $chunk;
    }

    public function write(string $bytes): void
    {
        $this->getBytes();

        throw new RuntimeException('The stream is read-only.');
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        $bytes = $this->getBytes();
        $base = match ($whence) {
            SEEK_SET => 0,
            SEEK_CUR => $this->position,
            SEEK_END => strlen($bytes),
            default => throw new InvalidArgumentException('Invalid seek origin.'),
        };

        if ($offset < -$base || $offset > PHP_INT_MAX - $base) {
            throw new RuntimeException('The requested stream position is out of range.');
        }

        $this->position = $base + $offset;
    }

    public function tell(): int
    {
        $this->getBytes();

        return $this->position;
    }

    public function flush(): void
    {
        $this->getBytes();
    }

    public function close(): void
    {
        $this->bytes = null;
    }

    private function getBytes(): string
    {
        return $this->bytes ?? throw new RuntimeException('The stream is closed.');
    }
}
