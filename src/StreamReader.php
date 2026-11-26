<?php

declare(strict_types=1);

namespace Light\IO;

use InvalidArgumentException;

final class StreamReader implements TextReader
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

    public function readLine(): ?string
    {
        $line = $this->stream->readLine();
        if ($line === null) {
            return null;
        }

        if (str_ends_with($line, "\r\n")) {
            return substr($line, 0, -2);
        }

        return str_ends_with($line, "\n") ? substr($line, 0, -1) : $line;
    }

    public function readToEnd(): string
    {
        return $this->stream->readToEnd();
    }

    public function close(): void
    {
        $this->stream->close();
    }
}
