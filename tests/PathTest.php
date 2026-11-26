<?php

declare(strict_types=1);

namespace Light\IO\Tests;

use InvalidArgumentException;
use Light\IO\Path;
use PHPUnit\Framework\TestCase;

final class PathTest extends TestCase
{
    public function testCombinesAndReplacesWithARootedPath(): void
    {
        $root = DIRECTORY_SEPARATOR === '\\' ? 'C:\\' : '/';
        self::assertSame('uploads' . DIRECTORY_SEPARATOR . 'report.csv', Path::combine('uploads', '', 'report.csv'));
        self::assertSame($root . 'report.csv', Path::combine('uploads', $root . 'report.csv'));
        self::assertTrue(Path::isPathRooted($root));
        self::assertFalse(Path::isPathRooted('report.csv'));
    }

    public function testSplitsAndChangesFileExtensions(): void
    {
        self::assertSame('report.csv', Path::getFileName('uploads/report.csv'));
        self::assertSame('', Path::getFileName('uploads/'));
        self::assertSame('.csv', Path::getExtension('uploads/report.csv'));
        self::assertSame('', Path::getExtension('uploads.v1/report.'));
        self::assertSame('uploads/report.json', Path::changeExtension('uploads/report.csv', 'json'));
        self::assertSame('uploads/report', Path::changeExtension('uploads/report.csv', null));
    }

    public function testNormalizesAndRelativizesPathsWithoutFilesystemAccess(): void
    {
        $root = DIRECTORY_SEPARATOR === '\\' ? 'C:\\' : '/';
        $base = $root . 'light-io-missing';
        $target = Path::getFullPath('uploads/.././report.csv', $base);
        self::assertSame($base . DIRECTORY_SEPARATOR . 'report.csv', $target);
        self::assertSame('report.csv', Path::getRelativePath($base, $target));
        self::assertSame('..' . DIRECTORY_SEPARATOR . 'report.csv', Path::getRelativePath(Path::combine($base, 'uploads'), $target));
        self::assertSame('.', Path::getRelativePath($base, $base));
        self::assertSame($root, Path::getFullPath($root . '../..'));
    }

    public function testRejectsARelativeBasePath(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Path::getFullPath('report.csv', 'uploads');
    }
}
