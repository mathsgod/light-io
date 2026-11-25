<?php

declare(strict_types=1);

namespace Light\IO;

final class StringReader implements TextReader
{
    private int $offset = 0;

    public function __construct(private readonly string $text)
    {
    }

    public function readLine(): ?string
    {
        if ($this->offset === strlen($this->text)) {
            return null;
        }

        $newline = strpos($this->text, "\n", $this->offset);
        if ($newline === false) {
            return $this->readToEnd();
        }

        $length = $newline - $this->offset;
        if ($length > 0 && $this->text[$newline - 1] === "\r") {
            --$length;
        }

        $line = substr($this->text, $this->offset, $length);
        $this->offset = $newline + 1;

        return $line;
    }

    public function readToEnd(): string
    {
        $remaining = substr($this->text, $this->offset);
        $this->offset = strlen($this->text);

        return $remaining;
    }
}
