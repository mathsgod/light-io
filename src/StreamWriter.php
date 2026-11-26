<?php

declare(strict_types=1);

namespace Light\IO;

use InvalidArgumentException;

final class StreamWriter implements TextWriter
{
    private readonly Stream $stream;

    /** @param Stream|resource $stream */
    public function __construct(mixed $stream, private readonly ?TextEncoding $encoding = null)
    {
        EncodedText::requireSupport($encoding ?? TextEncoding::Utf8);

        $this->stream = $stream instanceof Stream ? $stream : Stream::fromResource($stream);
        if (!$this->stream->canWrite()) {
            throw new InvalidArgumentException('Expected a writable stream.');
        }
    }

    public function write(string $text): void
    {
        if ($text !== '') {
            $this->stream->write($this->encoding === null ? $text : EncodedText::encode($text, $this->encoding));
        }
    }

    public function writeChar(string $character): void
    {
        EncodedText::validateChar($character);
        $this->write($character);
    }

    public function close(): void
    {
        $this->stream->close();
    }
}
