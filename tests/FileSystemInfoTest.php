<?php

declare(strict_types=1);

namespace Light\IO\Tests;

use InvalidArgumentException;
use Light\IO\DirectoryInfo;
use Light\IO\FileInfo;
use Light\IO\FileSystemInfo;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class FileSystemInfoTest extends TestCase
{
    public function testCreatesAndInspectsFilesAndDirectories(): void
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'light-io-' . bin2hex(random_bytes(8));
        $root = new DirectoryInfo($path);
        $child = new DirectoryInfo($path . DIRECTORY_SEPARATOR . 'child');
        $file = new FileInfo($path . DIRECTORY_SEPARATOR . 'note.txt');

        self::assertFalse($root->exists());
        self::assertFalse($file->exists());

        try {
            $child->create();
            $writer = $file->createText();
            try {
                $writer->write("字\0");
            } finally {
                $writer->close();
            }

            $files = iterator_to_array($root->enumerateFiles(), false);
            $directories = iterator_to_array($root->enumerateDirectories(), false);
            self::assertCount(1, $files);
            self::assertCount(1, $directories);
            self::assertInstanceOf(FileInfo::class, $files[0]);
            self::assertInstanceOf(DirectoryInfo::class, $directories[0]);
            self::assertInstanceOf(FileSystemInfo::class, $files[0]);
            self::assertSame($file->fullName(), $files[0]->fullName());
            self::assertSame('note.txt', $files[0]->name());
            self::assertSame('child', $directories[0]->name());
            self::assertSame(4, $file->length());
            self::assertTrue(touch($file->fullName(), 1700000000));
            self::assertSame(1700000000, $file->lastWriteTime());
            self::assertFalse((new FileInfo($child->fullName()))->exists());
            self::assertFalse((new DirectoryInfo($file->fullName()))->exists());

            $reader = $file->openText();
            try {
                self::assertSame("字\0", $reader->readToEnd());
            } finally {
                $reader->close();
            }

            $file->delete();
            $child->delete();
            $root->delete();
            self::assertFalse($file->exists());
            self::assertFalse($root->exists());
        } finally {
            if ($file->exists()) {
                unlink($file->fullName());
            }
            if ($child->exists()) {
                rmdir($child->fullName());
            }
            if ($root->exists()) {
                rmdir($root->fullName());
            }
        }
    }

    public function testRelativePathIsAnchoredAtConstruction(): void
    {
        $cwd = getcwd();
        self::assertIsString($cwd);
        $file = new FileInfo('composer.json');

        try {
            self::assertTrue(chdir(sys_get_temp_dir()));
            self::assertSame($cwd . DIRECTORY_SEPARATOR . 'composer.json', $file->fullName());
            self::assertTrue($file->exists());
        } finally {
            chdir($cwd);
        }
    }

    public function testRejectsInvalidPath(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new FileInfo("invalid\0path");
    }

    public function testLengthThrowsForMissingFile(): void
    {
        $file = new FileInfo(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'light-io-' . bin2hex(random_bytes(8)));

        $this->expectException(RuntimeException::class);
        $file->length();
    }
}
