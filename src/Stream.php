<?php

declare(strict_types=1);

namespace Light\IO;

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
