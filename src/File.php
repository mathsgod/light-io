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

    public static function createText(string $path): StreamWriter
    {
        return new StreamWriter(self::open($path, FileMode::Create, FileAccess::Write));
    }

    public static function openText(string $path): StreamReader
    {
        return new StreamReader(self::openRead($path));
    }

    public static function appendText(string $path): StreamWriter
    {
        return new StreamWriter(self::open($path, FileMode::Append, FileAccess::Write));
    }

    public static function openRead(string $path): FileStream
    {
        return self::open($path, FileMode::Open, FileAccess::Read);
    }

    public static function openWrite(string $path): FileStream
    {
        return self::open($path, FileMode::OpenOrCreate, FileAccess::Write);
    }

    public static function open(string $path, FileMode $mode, ?FileAccess $access = null): FileStream
    {
        return new FileStream($path, $mode, $access);
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
                self::copyBytes($input, $output);
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

    private static function copyBytes(Stream $input, Stream $output): void
    {
        while (($bytes = $input->read(8192)) !== '') {
            $output->write($bytes);
        }

        $output->flush();
    }

    private static function validatePath(string $path): void
    {
        if ($path === '' || str_contains($path, "\0")) {
            throw new InvalidArgumentException('Expected a non-empty file path without null bytes.');
        }
    }
}
