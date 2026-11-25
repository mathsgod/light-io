<?php

declare(strict_types=1);

namespace Light\IO\Tests;

use InvalidArgumentException;
use Light\IO\Directory;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DirectoryTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'light-io-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->root));
    }

    protected function tearDown(): void
    {
        foreach (['child/nested.txt', 'file.txt'] as $file) {
            $path = $this->path($file);
            if (is_file($path)) {
                unlink($path);
            }
        }

        foreach (['child/nested', 'child'] as $directory) {
            $path = $this->path($directory);
            if (is_dir($path)) {
                rmdir($path);
            }
        }

        rmdir($this->root);
    }

    public function testCreatesParentDirectoriesAndAcceptsExistingDirectory(): void
    {
        $path = $this->path('child/nested');
        self::assertFalse(Directory::exists($path));

        Directory::createDirectory($path);
        Directory::createDirectory($path);

        self::assertTrue(Directory::exists($path));
        self::assertTrue(Directory::exists($this->path('child')));
    }

    public function testEnumeratesImmediateFilesAndDirectories(): void
    {
        $files = Directory::enumerateFiles($this->root);
        $directories = Directory::enumerateDirectories($this->root);

        Directory::createDirectory($this->path('child/nested'));
        file_put_contents($this->path('child/nested.txt'), 'nested');
        file_put_contents($this->path('file.txt'), 'file');

        self::assertFalse(Directory::exists($this->path('file.txt')));
        self::assertSame([$this->path('file.txt')], iterator_to_array($files, false));
        self::assertSame([$this->path('child')], iterator_to_array($directories, false));
    }

    public function testDeletesEmptyDirectory(): void
    {
        $path = $this->path('child');
        Directory::createDirectory($path);

        Directory::delete($path);

        self::assertFalse(Directory::exists($path));
    }

    public function testRejectsDeletionOfNonEmptyDirectory(): void
    {
        Directory::createDirectory($this->path('child/nested'));

        $this->expectException(RuntimeException::class);
        Directory::delete($this->path('child'));
    }

    public function testThrowsWhenADirectoryCannotBeCreated(): void
    {
        $path = $this->path('file.txt');
        file_put_contents($path, 'file');

        $this->expectException(RuntimeException::class);
        Directory::createDirectory($path);
    }

    public function testThrowsWhenADirectoryCannotBeOpened(): void
    {
        $entries = Directory::enumerateFiles($this->path('missing'));

        $this->expectException(RuntimeException::class);
        iterator_to_array($entries);
    }

    public function testRejectsInvalidPath(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Directory::exists("invalid\0path");
    }

    private function path(string $relative): string
    {
        return $this->root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }
}
