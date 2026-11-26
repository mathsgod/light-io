<?php

declare(strict_types=1);

namespace Light\IO;

use InvalidArgumentException;
use RuntimeException;

final class BufferedStream extends Stream
{
    private readonly Stream $stream;
    private string $readBuffer = '';
    private int $readOffset = 0;
    private string $writeBuffer = '';
    private bool $closed = false;

    /** @param Stream|resource $stream */
    public function __construct(mixed $stream, private readonly int $bufferSize = 4096)
    {
        if ($bufferSize < 1) {
            throw new InvalidArgumentException('Expected a positive buffer size.');
        }

        $this->stream = $stream instanceof Stream ? $stream : Stream::fromResource($stream);
        if (!$this->stream->canRead() && !$this->stream->canWrite()) {
            throw new InvalidArgumentException('Expected a readable or writable stream.');
        }
    }

    public function canRead(): bool
    {
        return !$this->closed && $this->stream->canRead();
    }

    public function canWrite(): bool
    {
        return !$this->closed && $this->stream->canWrite();
    }

    public function canSeek(): bool
    {
        return !$this->closed && $this->stream->canSeek();
    }

    public function read(int $count): string
    {
        if ($count < 0) {
            throw new InvalidArgumentException('Expected a non-negative byte count.');
        }

        if (!$this->canRead()) {
            throw new RuntimeException('The stream is not readable.');
        }

        if ($count === 0) {
            return '';
        }

        $this->drainWrites();
        if ($this->unreadBytes() === 0) {
            if ($count >= $this->bufferSize) {
                return $this->stream->read($count);
            }

            $this->readBuffer = $this->stream->read($this->bufferSize);
            $this->readOffset = 0;
        }

        $bytes = substr($this->readBuffer, $this->readOffset, $count);
        $this->readOffset += strlen($bytes);

        return $bytes;
    }

    public function write(string $bytes): void
    {
        if (!$this->canWrite()) {
            throw new RuntimeException('The stream is not writable.');
        }

        if ($bytes === '') {
            return;
        }

        if ($this->unreadBytes() > 0) {
            $this->seek(0, SEEK_CUR);
        }

        $length = strlen($bytes);
        if ($length > $this->bufferSize - strlen($this->writeBuffer)) {
            $this->drainWrites();
        }

        if ($length >= $this->bufferSize) {
            $this->stream->write($bytes);
            return;
        }

        $this->writeBuffer .= $bytes;
        if (strlen($this->writeBuffer) === $this->bufferSize) {
            $this->drainWrites();
        }
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        if (!$this->canSeek()) {
            throw new RuntimeException('The stream does not support seeking.');
        }

        $this->drainWrites();
        if ($whence === SEEK_CUR) {
            $offset -= $this->unreadBytes();
        }

        $this->stream->seek($offset, $whence);
        $this->readBuffer = '';
        $this->readOffset = 0;
    }

    public function tell(): int
    {
        if (!$this->canSeek()) {
            throw new RuntimeException('The stream does not support seeking.');
        }

        return $this->stream->tell() - $this->unreadBytes() + strlen($this->writeBuffer);
    }

    public function flush(): void
    {
        if ($this->closed) {
            throw new RuntimeException('The stream is closed.');
        }

        $this->drainWrites();
        if ($this->stream->canWrite()) {
            $this->stream->flush();
        }
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }

        try {
            $this->flush();
        } finally {
            $this->closed = true;
            $this->readBuffer = '';
            $this->readOffset = 0;
            $this->stream->close();
        }
    }

    private function unreadBytes(): int
    {
        return strlen($this->readBuffer) - $this->readOffset;
    }

    private function drainWrites(): void
    {
        if ($this->writeBuffer === '') {
            return;
        }

        $bytes = $this->writeBuffer;
        // A failed write may have consumed part of the buffer.
        $this->writeBuffer = '';
        $this->stream->write($bytes);
    }
}
