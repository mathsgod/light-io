<?php

declare(strict_types=1);

namespace Light\IO;

use InvalidArgumentException;
use RuntimeException;

final class File
{
    private function __construct()
    {
    }

    public static function createText(string $path): StreamWriter
    {
        self::validatePath($path);

        $stream = @fopen($path, 'wb');
        if ($stream === false) {
            throw new RuntimeException('Could not open the file for writing.');
        }

        return new StreamWriter($stream);
    }

    public static function openText(string $path): StreamReader
    {
        self::validatePath($path);

        if (is_dir($path)) {
            throw new RuntimeException('Expected a file path, got a directory.');
        }

        $stream = @fopen($path, 'rb');
        if ($stream === false) {
            throw new RuntimeException('Could not open the file for reading.');
        }

        return new StreamReader($stream);
    }

    private static function validatePath(string $path): void
    {
        if ($path === '' || str_contains($path, "\0")) {
            throw new InvalidArgumentException('Expected a non-empty file path without null bytes.');
        }
    }
}
