<?php

declare(strict_types=1);

namespace Light\IO;

use Generator;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class File
{
    private function __construct()
    {
    }

    public static function create(string $path): FileStream
    {
        return self::open($path, FileMode::Create);
    }

    public static function createText(string $path, ?TextEncoding $encoding = null): StreamWriter
    {
        EncodedText::requireSupport($encoding ?? TextEncoding::Utf8);

        return new StreamWriter(self::open($path, FileMode::Create, FileAccess::Write), $encoding);
    }

    public static function openText(string $path, ?TextEncoding $encoding = null, bool $detectBom = false): StreamReader
    {
        return new StreamReader(self::openRead($path), $encoding, $detectBom);
    }

    public static function appendText(string $path, ?TextEncoding $encoding = null): StreamWriter
    {
        EncodedText::requireSupport($encoding ?? TextEncoding::Utf8);

        return new StreamWriter(self::open($path, FileMode::Append, FileAccess::Write), $encoding);
    }

    public static function openRead(string $path): FileStream
    {
        return self::open($path, FileMode::Open, FileAccess::Read);
    }

    public static function openWrite(string $path): FileStream
    {
        return self::open($path, FileMode::OpenOrCreate, FileAccess::Write);
    }

    public static function open(string $path, FileMode $mode, ?FileAccess $access = null, ?int $share = null): FileStream
    {
        return new FileStream($path, $mode, $access, $share);
    }

    public static function getAttributes(string $path): int
    {
        self::validatePath($path);
        if (PHP_OS_FAMILY === 'Windows') {
            throw new RuntimeException('File attributes are currently supported on Unix-like systems.');
        }

        clearstatcache();
        $stat = @lstat($path);
        if ($stat === false) {
            throw new RuntimeException('Could not read file attributes.');
        }

        $attributes = 0;
        if (($stat['mode'] & 0222) === 0) {
            $attributes |= FileAttributes::ReadOnly;
        }

        $name = basename($path);
        if ($name !== '.' && $name !== '..' && str_starts_with($name, '.')) {
            $attributes |= FileAttributes::Hidden;
        }

        if (is_dir($path)) {
            $attributes |= FileAttributes::Directory;
        }

        if (is_link($path)) {
            $attributes |= FileAttributes::ReparsePoint;
        }

        return $attributes ?: FileAttributes::Normal;
    }

    public static function setAttributes(string $path, int $attributes): void
    {
        $current = self::getAttributes($path);
        $fixed = FileAttributes::Hidden | FileAttributes::Directory | FileAttributes::ReparsePoint;
        if (($attributes & ~(FileAttributes::Normal | FileAttributes::ReadOnly | $fixed)) !== 0
            || (($attributes ^ $current) & $fixed) !== 0
            || ($current & FileAttributes::ReparsePoint) !== 0
        ) {
            throw new InvalidArgumentException('Only the read-only attribute can be changed on a non-symbolic-link path.');
        }

        if ((($attributes ^ $current) & FileAttributes::ReadOnly) === 0) {
            return;
        }

        $mode = self::getUnixFileMode($path);
        self::setUnixFileMode($path, ($attributes & FileAttributes::ReadOnly) !== 0
            ? $mode & ~0222
            : $mode | UnixFileMode::UserWrite);
    }

    public static function getUnixFileMode(string $path): int
    {
        self::validatePath($path);
        self::requireUnixFileModes();

        clearstatcache();
        $mode = @fileperms($path);
        if ($mode === false) {
            throw new RuntimeException('Could not read Unix file permissions.');
        }

        return $mode & 07777;
    }

    public static function setUnixFileMode(string $path, int $mode): void
    {
        self::validatePath($path);
        if ($mode < 0 || $mode > 07777) {
            throw new InvalidArgumentException('Expected only Unix permission and special mode bits.');
        }

        self::requireUnixFileModes();
        if (!@chmod($path, $mode)) {
            throw new RuntimeException('Could not set Unix file permissions.');
        }
    }

    private static function requireUnixFileModes(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            throw new RuntimeException('Unix file permissions are unavailable on Windows.');
        }
    }

    public static function exists(string $path): bool
    {
        if ($path === '' || str_contains($path, "\0")) {
            return false;
        }

        clearstatcache();

        return is_file($path);
    }

    public static function delete(string $path): void
    {
        self::validatePath($path);
        if (@unlink($path)) {
            return;
        }

        clearstatcache();
        if (file_exists($path) || is_link($path)) {
            throw new RuntimeException('Could not delete the file.');
        }
    }

    public static function copy(string $source, string $destination, bool $overwrite = false): void
    {
        $input = self::openRead($source);
        try {
            self::validatePath($destination);
            if (is_dir($destination)) {
                throw new RuntimeException('Expected a destination file path, got a directory.');
            }

            if ($overwrite) {
                // Stage the copy so the source and existing destination survive a failed write.
                self::copyReplacing($input, $destination);
                return;
            }

            self::copyToNewFile($input, $destination);
        } finally {
            $input->close();
        }
    }

    public static function move(string $source, string $destination, bool $overwrite = false): void
    {
        self::validatePath($source);
        self::validatePath($destination);

        if (!is_file($source)) {
            throw new RuntimeException('Expected an existing source file.');
        }

        if ($overwrite) {
            if (!@rename($source, $destination)) {
                throw new RuntimeException('Could not move the file.');
            }
            return;
        }

        if (!@link($source, $destination)) {
            throw new RuntimeException('Could not move the file without overwriting.');
        }

        if (!@unlink($source)) {
            @unlink($destination);
            throw new RuntimeException('Could not remove the source file.');
        }
    }

    public static function readAllBytes(string $path): string
    {
        $stream = self::openRead($path);
        try {
            return $stream->readToEnd();
        } finally {
            $stream->close();
        }
    }

    public static function readAllText(string $path): string
    {
        return self::readAllBytes($path);
    }

    /** @return list<string> */
    public static function readAllLines(string $path): array
    {
        return iterator_to_array(self::readLines($path), false);
    }

    /** @return Generator<int, string> */
    public static function readLines(string $path): Generator
    {
        $reader = self::openText($path);
        try {
            while (($line = $reader->readLine()) !== null) {
                yield $line;
            }
        } finally {
            $reader->close();
        }
    }

    public static function writeAllBytes(string $path, string $bytes): void
    {
        $stream = self::open($path, FileMode::Create, FileAccess::Write);
        try {
            $stream->write($bytes);
        } finally {
            $stream->close();
        }
    }

    public static function writeAllText(string $path, string $text): void
    {
        self::writeAllBytes($path, $text);
    }

    public static function appendAllText(string $path, string $text): void
    {
        $writer = self::appendText($path);
        try {
            $writer->write($text);
        } finally {
            $writer->close();
        }
    }

    /** @param iterable<string> $lines */
    public static function writeAllLines(string $path, iterable $lines): void
    {
        self::writeLines(self::createText($path), $lines);
    }

    /** @param iterable<string> $lines */
    public static function appendAllLines(string $path, iterable $lines): void
    {
        self::writeLines(self::appendText($path), $lines);
    }

    /** @param iterable<string> $lines */
    private static function writeLines(StreamWriter $writer, iterable $lines): void
    {
        try {
            foreach ($lines as $line) {
                $writer->write($line . PHP_EOL);
            }
        } finally {
            $writer->close();
        }
    }

    private static function copyToNewFile(Stream $input, string $destination): void
    {
        $output = self::open($destination, FileMode::CreateNew, FileAccess::Write);
        try {
            try {
                $input->copyTo($output);
                $output->flush();
            } finally {
                $output->close();
            }
        } catch (Throwable $error) {
            @unlink($destination);
            throw $error;
        }
    }

    private static function copyReplacing(Stream $input, string $destination): void
    {
        $directory = realpath(dirname($destination));
        if ($directory === false || !is_dir($directory)) {
            throw new RuntimeException('Could not resolve the destination directory.');
        }

        $temporary = $directory . DIRECTORY_SEPARATOR . '.light-io-' . bin2hex(random_bytes(16));
        self::copyToNewFile($input, $temporary);
        try {
            if (!@rename($temporary, $destination)) {
                throw new RuntimeException('Could not replace the destination file.');
            }
        } finally {
            if (file_exists($temporary)) {
                @unlink($temporary);
            }
        }
    }

    private static function validatePath(string $path): void
    {
        if ($path === '' || str_contains($path, "\0")) {
            throw new InvalidArgumentException('Expected a non-empty file path without null bytes.');
        }
    }
}
