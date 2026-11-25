<?php

declare(strict_types=1);

namespace Light\IO\Tests;

use InvalidArgumentException;
use Light\IO\File;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class FileTest extends TestCase
{
    public function testOpenTextReadsAFileCreatedByCreateText(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'light-io-');
        self::assertIsString($path);

        try {
            $writer = File::createText($path);
            try {
                $writer->write("first\r\n\n字\0\xFF\r");
            } finally {
                $writer->close();
            }

            $reader = File::openText($path);
            try {
                self::assertSame('first', $reader->readLine());
                self::assertSame('', $reader->readLine());
                self::assertSame("字\0\xFF\r", $reader->readToEnd());
                self::assertNull($reader->readLine());
                self::assertSame('', $reader->readToEnd());
            } finally {
                $reader->close();
            }
        } finally {
            unlink($path);
        }
    }

    public function testOpenTextThrowsWhenFileIsMissing(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'light-io-');
        self::assertIsString($path);
        unlink($path);

        $this->expectException(RuntimeException::class);
        File::openText($path);
    }

    public function testOpenTextRejectsDirectory(): void
    {
        $this->expectException(RuntimeException::class);
        File::openText(sys_get_temp_dir());
    }

    public function testCreateTextCreatesAndTruncatesFile(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'light-io-');
        self::assertIsString($path);
        unlink($path);

        try {
            $writer = File::createText($path);
            try {
                $writer->write('first');
                $writer->write("字\0\xFF");
            } finally {
                $writer->close();
            }
            self::assertSame("first字\0\xFF", file_get_contents($path));

            $writer = File::createText($path);
            try {
                self::assertSame('', file_get_contents($path));
                $writer->write('new');
            } finally {
                $writer->close();
            }
            $writer->close();
            self::assertSame('new', file_get_contents($path));

            $this->expectException(RuntimeException::class);
            $writer->write('closed');
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testCreateTextRejectsAnEmptyPath(): void
    {
        $this->expectException(InvalidArgumentException::class);
        File::createText('');
    }

    public function testCreateTextThrowsWhenThePathCannotBeOpened(): void
    {
        $this->expectException(RuntimeException::class);
        File::createText(sys_get_temp_dir());
    }
}
