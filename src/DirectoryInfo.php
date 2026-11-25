<?php

declare(strict_types=1);

namespace Light\IO;

final class DirectoryInfo extends FileSystemInfo
{
    public function exists(): bool
    {
        clearstatcache();

        return Directory::exists($this->fullName());
    }

    public function create(): void
    {
        Directory::createDirectory($this->fullName());
    }

    /** @return iterable<FileInfo> */
    public function enumerateFiles(): iterable
    {
        foreach (Directory::enumerateFiles($this->fullName()) as $path) {
            yield new FileInfo($path);
        }
    }

    /** @return iterable<DirectoryInfo> */
    public function enumerateDirectories(): iterable
    {
        foreach (Directory::enumerateDirectories($this->fullName()) as $path) {
            yield new self($path);
        }
    }

    public function delete(): void
    {
        Directory::delete($this->fullName());
    }
}
