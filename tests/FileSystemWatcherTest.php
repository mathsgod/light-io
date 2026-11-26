<?php

declare(strict_types=1);

namespace Light\IO\Tests;

use Light\IO\FileSystemChangeType;
use Light\IO\FileSystemEvent;
use Light\IO\FileSystemWatcher;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class FileSystemWatcherTest extends TestCase
{
    public function testWatchingRequiresTheOptionalExtension(): void
    {
        if (extension_loaded('inotify')) {
            self::markTestSkipped('This case runs without inotify.');
        }

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('requires the PHP inotify extension');
        new FileSystemWatcher(sys_get_temp_dir());
    }

    public function testWatchesNewSubdirectoriesAndPairsRenames(): void
    {
        if (!extension_loaded('inotify')) {
            self::markTestSkipped('The optional inotify extension is unavailable.');
        }

        $root = sys_get_temp_dir() . '/light-io-watch-' . bin2hex(random_bytes(8));
        $child = $root . '/child';
        $first = $child . '/first';
        $second = $child . '/second';
        $third = $child . '/third';
        mkdir($root);
        $watcher = null;

        try {
            $watcher = new FileSystemWatcher($root, includeSubdirectories: true);
            self::assertSame([], $watcher->readEvents());
            mkdir($child);
            self::assertEquals([new FileSystemEvent(FileSystemChangeType::Created, $child, true)], $watcher->readEvents(500));

            file_put_contents($first, 'data');
            $events = $watcher->readEvents(500);
            self::assertContainsEquals(new FileSystemEvent(FileSystemChangeType::Created, $first, false), $events);
            self::assertContainsEquals(new FileSystemEvent(FileSystemChangeType::Changed, $first, false), $events);

            rename($first, $second);
            rename($second, $third);
            self::assertEquals([
                new FileSystemEvent(FileSystemChangeType::Renamed, $second, false, $first),
                new FileSystemEvent(FileSystemChangeType::Renamed, $third, false, $second),
            ], $watcher->readEvents(500));

            unlink($third);
            self::assertEquals([new FileSystemEvent(FileSystemChangeType::Deleted, $third, false)], $watcher->readEvents(500));
        } finally {
            $watcher?->close();
            foreach ([$first, $second, $third] as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }

            if (is_dir($child)) {
                rmdir($child);
            }
            rmdir($root);
        }
    }
}
