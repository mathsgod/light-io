<?php

declare(strict_types=1);

namespace Light\IO;

use InvalidArgumentException;
use RuntimeException;

abstract class FileSystemInfo
{
    private readonly string $path;

    public function __construct(string $path)
    {
        if ($path === '' || str_contains($path, "\0")) {
            throw new InvalidArgumentException('Expected a non-empty path without null bytes.');
        }

        $this->path = self::absolutePath($path);
    }

    public function name(): string
    {
        return basename($this->path);
    }

    public function fullName(): string
    {
        return $this->path;
    }

    public function lastWriteTime(): int
    {
        $time = $this->exists() ? @filemtime($this->path) : false;
        if ($time === false) {
            throw new RuntimeException('Could not determine the last write time.');
        }

        return $time;
    }

    abstract public function exists(): bool;

    abstract public function delete(): void;

    private static function absolutePath(string $path): string
    {
        $windows = DIRECTORY_SEPARATOR === '\\';
        if (str_starts_with($path, $windows ? '\\\\' : '/')
            || ($windows && preg_match('~^(?:[a-z]:[\\\\/]|//)~i', $path) === 1)
        ) {
            return $path;
        }

        $cwd = getcwd();
        if ($cwd === false) {
            throw new RuntimeException('Could not determine the current directory.');
        }

        if ($windows) {
            if (preg_match('~^[a-z]:~i', $path) === 1) {
                throw new InvalidArgumentException('Use an absolute path for a drive-qualified path.');
            }

            if ($path[0] === '\\' || $path[0] === '/') {
                if (preg_match('~^[a-z]:~i', $cwd) !== 1) {
                    throw new InvalidArgumentException('Use a fully qualified path from a network share.');
                }

                return substr($cwd, 0, 2) . $path;
            }
        }

        return rtrim($cwd, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $path;
    }
}
