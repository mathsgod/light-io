<?php

declare(strict_types=1);

namespace Light\IO;

use InvalidArgumentException;
use RuntimeException;

final class StreamWriter implements TextWriter
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
        if (strpbrk($mode, 'waxc+') === false) {
            throw new InvalidArgumentException('Expected a writable stream.');
        }

        $this->stream = $stream;
    }

    public function write(string $text): void
    {
        if ($text === '') {
            return;
        }

        if (!is_resource($this->stream)) {
            throw new RuntimeException('The stream is closed.');
        }

        $length = strlen($text);
        $offset = 0;

        while ($offset < $length) {
            $written = @fwrite($this->stream, substr($text, $offset, 8192));
            if ($written === false || $written === 0) {
                throw new RuntimeException('Could not finish writing to the stream.');
            }

            $offset += $written;
        }
    }
}
