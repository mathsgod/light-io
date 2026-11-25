<?php

declare(strict_types=1);

namespace Light\IO;

use RuntimeException;

final class FileInfo extends FileSystemInfo
{
    public function exists(): bool
    {
        clearstatcache();

        return is_file($this->fullName());
    }

    public function length(): int
    {
        $length = $this->exists() ? @filesize($this->fullName()) : false;
        if ($length === false) {
            throw new RuntimeException('Could not determine the file length.');
        }

        return $length;
    }

    public function openText(): StreamReader
    {
        return File::openText($this->fullName());
    }

    public function createText(): StreamWriter
    {
        return File::createText($this->fullName());
    }

    public function delete(): void
    {
        if (!@unlink($this->fullName())) {
            throw new RuntimeException('Could not delete the file.');
        }
    }
}
