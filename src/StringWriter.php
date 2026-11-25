<?php

declare(strict_types=1);

namespace Light\IO;

final class StringWriter implements TextWriter
{
    private string $text = '';

    public function write(string $text): void
    {
        $this->text .= $text;
    }

    public function toString(): string
    {
        return $this->text;
    }
}
