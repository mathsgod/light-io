<?php

declare(strict_types=1);

namespace Light\IO;

use InvalidArgumentException;
use RuntimeException;

final class StreamReader implements TextReader
{
    private readonly Stream $stream;

    private string $prefix = '';
    private bool $detectBom;

    /** @param Stream|resource $stream */
    public function __construct(
        mixed $stream,
        private ?TextEncoding $encoding = null,
        bool $detectBom = false,
    ) {
        $this->detectBom = $detectBom;
        if ($encoding !== null) {
            EncodedText::requireSupport($encoding);
        }

        $this->stream = $stream instanceof Stream ? $stream : Stream::fromResource($stream);
        if (!$this->stream->canRead()) {
            throw new InvalidArgumentException('Expected a readable stream.');
        }
    }

    public function readLine(): ?string
    {
        $this->detectEncoding();
        $line = $this->encoding === null ? $this->stream->readLine() : $this->readDecodedLine();
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
        $this->detectEncoding();
        $bytes = $this->prefix . $this->stream->readToEnd();
        $this->prefix = '';

        return $this->encoding === null ? $bytes : EncodedText::decode($bytes, $this->encoding);
    }

    public function close(): void
    {
        $this->stream->close();
    }

    public function readChar(): ?string
    {
        $this->detectEncoding();

        return EncodedText::readChar($this->readEncodedBytes(...), $this->encoding ?? TextEncoding::Utf8);
    }

    public function readChars(int $count): string
    {
        if ($count < 0) {
            throw new InvalidArgumentException('Expected a non-negative character count.');
        }

        $text = '';
        while ($count-- > 0 && ($character = $this->readChar()) !== null) {
            $text .= $character;
        }

        return $text;
    }

    private function readDecodedLine(): ?string
    {
        $line = '';
        while (($character = $this->readChar()) !== null) {
            $line .= $character;
            if ($character === "\n") {
                return $line;
            }
        }

        return $line === '' ? null : $line;
    }

    private function detectEncoding(): void
    {
        if (!$this->detectBom) {
            return;
        }

        $this->detectBom = false;
        $this->encoding ??= TextEncoding::Utf8;
        $this->prefix = $this->readEncodedBytes(4);
        foreach ([
            "\x00\x00\xFE\xFF" => TextEncoding::Utf32BE,
            "\xFF\xFE\x00\x00" => TextEncoding::Utf32LE,
            "\xEF\xBB\xBF" => TextEncoding::Utf8,
            "\xFE\xFF" => TextEncoding::Utf16BE,
            "\xFF\xFE" => TextEncoding::Utf16LE,
        ] as $bom => $encoding) {
            if (str_starts_with($this->prefix, $bom)) {
                $this->encoding = $encoding;
                $this->prefix = substr($this->prefix, strlen($bom));
                return;
            }
        }
    }

    private function readEncodedBytes(int $count): string
    {
        if (!$this->stream->canRead()) {
            throw new RuntimeException('The stream is not readable.');
        }

        $bytes = substr($this->prefix, 0, $count);
        $this->prefix = substr($this->prefix, strlen($bytes));
        while (strlen($bytes) < $count) {
            $chunk = $this->stream->read($count - strlen($bytes));
            if ($chunk === '') {
                break;
            }

            $bytes .= $chunk;
        }

        return $bytes;
    }
}
