<?php

declare(strict_types=1);

namespace Light\IO;

interface TextWriter
{
    public function write(string $text): void;
}
