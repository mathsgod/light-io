<?php

declare(strict_types=1);

namespace Light\IO;

use FilesystemIterator;
use InvalidArgumentException;
use RuntimeException;
use UnexpectedValueException;

final class Directory
{
    private function __construct()
    {
    }

    public static function exists(string $path): bool
    {
        self::validatePath($path);

        return is_dir($path);
    }

    public static function createDirectory(string $path): void
    {
        self::validatePath($path);

        if (!is_dir($path) && !@mkdir($path, 0777, true) && !is_dir($path)) {
            throw new RuntimeException('Could not create the directory.');
        }
    }

    /** @return iterable<string> */
    public static function enumerateFiles(string $path): iterable
    {
        return self::enumerate($path, directories: false);
    }

    /** @return iterable<string> */
    public static function enumerateDirectories(string $path): iterable
    {
        return self::enumerate($path, directories: true);
    }

    public static function delete(string $path): void
    {
        self::validatePath($path);

        if (!@rmdir($path)) {
            throw new RuntimeException('Could not delete the directory.');
        }
    }

    /** @return iterable<string> */
    private static function enumerate(string $path, bool $directories): iterable
    {
        self::validatePath($path);

        try {
            $entries = new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS);
        } catch (UnexpectedValueException $exception) {
            throw new RuntimeException('Could not open the directory.', 0, $exception);
        }

        foreach ($entries as $entry) {
            if ($directories ? $entry->isDir() : $entry->isFile()) {
                yield $entry->getPathname();
            }
        }
    }

    private static function validatePath(string $path): void
    {
        if ($path === '' || str_contains($path, "\0")) {
            throw new InvalidArgumentException('Expected a non-empty directory path without null bytes.');
        }
    }
}
