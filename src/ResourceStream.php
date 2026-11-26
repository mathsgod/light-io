<?php

declare(strict_types=1);

namespace Light\IO;

use InvalidArgumentException;
use RuntimeException;

final class ResourceStream extends Stream
{
    /** @var resource */
    private readonly mixed $resource;

    /** @param resource $resource */
    public function __construct(mixed $resource)
    {
        if (!is_resource($resource) || get_resource_type($resource) !== 'stream') {
            throw new InvalidArgumentException('Expected an open stream resource.');
        }

        $this->resource = $resource;
    }

    public function canRead(): bool
    {
        if (!is_resource($this->resource)) {
            return false;
        }

        $mode = stream_get_meta_data($this->resource)['mode'];
        return str_starts_with($mode, 'r') || str_contains($mode, '+');
    }

    public function canWrite(): bool
    {
        return is_resource($this->resource)
            && strpbrk(stream_get_meta_data($this->resource)['mode'], 'waxc+') !== false;
    }

    public function canSeek(): bool
    {
        return is_resource($this->resource) && stream_get_meta_data($this->resource)['seekable'];
    }

    public function read(int $count): string
    {
        if ($count < 0) {
            throw new InvalidArgumentException('Expected a non-negative byte count.');
        }

        $this->ensureOpen();
        if ($count === 0) {
            return '';
        }

        $bytes = @fread($this->resource, $count);
        if ($bytes === false || ($bytes === '' && !feof($this->resource))) {
            throw new RuntimeException('Could not finish reading from the stream.');
        }

        return $bytes;
    }

    public function readLine(): ?string
    {
        $this->ensureOpen();
        $line = @fgets($this->resource);
        if ($line === false) {
            if (feof($this->resource)) {
                return null;
            }

            throw new RuntimeException('Could not read a line from the stream.');
        }

        return $line;
    }

    public function readToEnd(): string
    {
        $this->ensureOpen();
        $bytes = @stream_get_contents($this->resource);
        if ($bytes === false || !feof($this->resource)) {
            throw new RuntimeException('Could not read to the end of the stream.');
        }

        return $bytes;
    }

    public function write(string $bytes): void
    {
        if ($bytes === '') {
            return;
        }

        $this->ensureOpen();
        $length = strlen($bytes);
        $offset = 0;
        while ($offset < $length) {
            $written = @fwrite($this->resource, substr($bytes, $offset, 8192));
            if ($written === false || $written === 0) {
                throw new RuntimeException('Could not finish writing to the stream.');
            }

            $offset += $written;
        }
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        $this->ensureOpen();
        if (@fseek($this->resource, $offset, $whence) !== 0) {
            throw new RuntimeException('Could not seek in the stream.');
        }
    }

    public function tell(): int
    {
        $this->ensureOpen();
        $position = @ftell($this->resource);
        if ($position === false) {
            throw new RuntimeException('Could not determine the stream position.');
        }

        return $position;
    }

    public function flush(): void
    {
        $this->ensureOpen();
        if (!@fflush($this->resource)) {
            throw new RuntimeException('Could not flush the stream.');
        }
    }

    public function close(): void
    {
        if (is_resource($this->resource) && !@fclose($this->resource)) {
            throw new RuntimeException('Could not close the stream.');
        }
    }

    private function ensureOpen(): void
    {
        if (!is_resource($this->resource)) {
            throw new RuntimeException('The stream is closed.');
        }
    }
}
