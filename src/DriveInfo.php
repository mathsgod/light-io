<?php

declare(strict_types=1);

namespace Light\IO;

use InvalidArgumentException;
use RuntimeException;

final class DriveInfo
{
    private readonly string $directory;

    public function __construct(string $directory)
    {
        if ($directory === '' || str_contains($directory, "\0")) {
            throw new InvalidArgumentException('Expected a non-empty directory path without null bytes.');
        }

        $path = @realpath($directory);
        if ($path === false || !is_dir($path)) {
            throw new RuntimeException('Could not resolve the drive directory.');
        }

        $this->directory = $path;
    }

    public function getTotalSize(): float
    {
        $bytes = @disk_total_space($this->directory);
        if ($bytes === false) {
            throw new RuntimeException('Could not determine the total drive size.');
        }

        return $bytes;
    }

    public function getAvailableFreeSpace(): float
    {
        $bytes = @disk_free_space($this->directory);
        if ($bytes === false) {
            throw new RuntimeException('Could not determine the available drive space.');
        }

        return $bytes;
    }
}
