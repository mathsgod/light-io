<?php

declare(strict_types=1);

namespace Light\IO;

use InvalidArgumentException;
use RuntimeException;

final class StreamReader implements TextReader
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

    public function readLine(): ?string
    {
        $this->ensureOpen();

        $line = @fgets($this->stream);
        if ($line === false) {
            if (feof($this->stream)) {
                return null;
            }

            throw new RuntimeException('Could not read a line from the stream.');
        }

        if (str_ends_with($line, "\r\n")) {
            return substr($line, 0, -2);
        }

        return str_ends_with($line, "\n") ? substr($line, 0, -1) : $line;
    }

    public function readToEnd(): string
    {
        $this->ensureOpen();

        $text = @stream_get_contents($this->stream);
        if ($text === false || !feof($this->stream)) {
            throw new RuntimeException('Could not read to the end of the stream.');
        }

        return $text;
    }

    public function close(): void
    {
        if (is_resource($this->stream) && !@fclose($this->stream)) {
            throw new RuntimeException('Could not close the stream.');
        }
    }

    private function ensureOpen(): void
    {
        if (!is_resource($this->stream)) {
            throw new RuntimeException('The stream is closed.');
        }
    }
}
