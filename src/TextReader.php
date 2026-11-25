<?php

declare(strict_types=1);

namespace Light\IO;

interface TextReader
{
    public function readLine(): ?string;

    public function readToEnd(): string;
}
