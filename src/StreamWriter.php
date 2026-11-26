<?php

declare(strict_types=1);

namespace Light\IO;

use InvalidArgumentException;

final class StreamWriter implements TextWriter
{
    private readonly Stream $stream;

    /** @param Stream|resource $stream */
    public function __construct(mixed $stream)
    {
        $this->stream = $stream instanceof Stream ? $stream : Stream::fromResource($stream);
        if (!$this->stream->canWrite()) {
            throw new InvalidArgumentException('Expected a writable stream.');
        }
    }

    public function write(string $text): void
    {
        if ($text !== '') {
            $this->stream->write($text);
        }
    }

    public function close(): void
    {
        $this->stream->close();
    }
}
