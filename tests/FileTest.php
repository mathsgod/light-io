<?php

declare(strict_types=1);

namespace Light\IO\Tests;

use InvalidArgumentException;
use Light\IO\File;
use Light\IO\FileAccess;
use Light\IO\FileMode;
use Light\IO\FileStream;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class FileTest extends TestCase
{
    public function testFileStreamResizesWithoutReadingStaleBufferedBytes(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'light-io-');
        try {
            File::writeAllBytes($path, 'abcdef');
            $stream = File::open($path, FileMode::Open);
            try {
                self::assertInstanceOf(FileStream::class, $stream);
                self::assertSame(6, $stream->length());
                self::assertSame('a', $stream->read(1));
                $stream->setLength(2);
                self::assertSame(1, $stream->tell());
                self::assertSame('b', $stream->read(10));
                $stream->setLength(4);
                self::assertSame(4, $stream->length());
                self::assertSame("\0\0", $stream->read(10));
            } finally {
                $stream->close();
            }

            $stream = File::openRead($path);
            try {
                $this->expectException(RuntimeException::class);
                $stream->setLength(0);
            } finally {
                $stream->close();
            }
        } finally {
            File::delete($path);
        }
    }

    public function testWholeFileOperationsPreserveBytesAndLineBoundaries(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'light-io-');
        try {
            File::writeAllBytes($path, "\0\xFF");
            self::assertSame("\0\xFF", File::readAllBytes($path));
            File::writeAllLines($path, ['first', '']);
            File::appendAllLines($path, ['third']);
            File::appendAllText($path, 'last');
            self::assertSame(['first', '', 'third', 'last'], File::readAllLines($path));
            File::delete($path);
            File::delete($path);
            self::assertFalse(File::exists($path));
        } finally {
            File::delete($path);
        }
    }

    public function testOpenWritePreservesExistingContents(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'light-io-');
        try {
            File::writeAllText($path, 'abc');
            $stream = File::open($path, FileMode::Open, FileAccess::Write);
            try {
                self::assertFalse($stream->canRead());
                self::assertTrue($stream->canWrite());
                $stream->write('X');
                self::assertSame('Xbc', File::readAllText($path));
                $this->expectException(RuntimeException::class);
                $stream->read(1);
            } finally {
                $stream->close();
            }
        } finally {
            File::delete($path);
        }
    }

    public function testCopyAndMovePreserveExistingDestinationsUnlessOverwriteIsEnabled(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'light-io-');
        $destination = tempnam(sys_get_temp_dir(), 'light-io-');
        try {
            File::writeAllText($source, 'source');
            File::writeAllText($destination, 'destination');
            foreach (['copy', 'move'] as $operation) {
                try {
                    File::$operation($source, $destination);
                    self::fail('Expected the occupied destination to be rejected.');
                } catch (RuntimeException) {
                    self::assertSame('destination', File::readAllText($destination));
                    self::assertSame('source', File::readAllText($source));
                }
            }

            File::copy($source, $destination, overwrite: true);
            self::assertSame('source', File::readAllText($destination));
            File::delete($destination);
            File::move($source, $destination);
            self::assertFalse(File::exists($source));
            self::assertSame('source', File::readAllText($destination));
        } finally {
            File::delete($source);
            File::delete($destination);
        }
    }

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
