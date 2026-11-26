<?php

declare(strict_types=1);

namespace Light\IO;

use FilesystemIterator;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;
use UnexpectedValueException;

final class FileSystemWatcher
{
    /** @var resource */
    private readonly mixed $stream;
    private readonly string $root;
    private int $rootWatch;
    /** @var array<int, string> */
    private array $paths = [];

    public function __construct(string $path, private readonly bool $includeSubdirectories = false)
    {
        if (!extension_loaded('inotify')) {
            throw new RuntimeException('FileSystemWatcher requires the PHP inotify extension.');
        }

        if ($path === '' || str_contains($path, "\0")) {
            throw new InvalidArgumentException('Expected a non-empty directory path without null bytes.');
        }

        $root = realpath($path);
        if ($root === false || !is_dir($root)) {
            throw new RuntimeException('Could not resolve the directory to watch.');
        }

        $stream = @inotify_init();
        if ($stream === false) {
            throw new RuntimeException('Could not initialize inotify.');
        }

        $this->stream = $stream;
        $this->root = $root;

        try {
            if (!stream_set_blocking($stream, false)) {
                throw new RuntimeException('Could not configure the inotify stream.');
            }

            $this->rootWatch = $this->addWatch($root);
            $this->paths = [$this->rootWatch => $root];
            if ($this->includeSubdirectories) {
                $this->refreshWatches();
            }
        } catch (Throwable $error) {
            $this->close();
            throw $error;
        }
    }

    /** @return list<FileSystemEvent> */
    public function readEvents(int $timeoutMilliseconds = 0): array
    {
        if ($timeoutMilliseconds < 0) {
            throw new InvalidArgumentException('Expected a non-negative timeout.');
        }

        if (!is_resource($this->stream)) {
            throw new RuntimeException('The watcher is closed.');
        }

        $read = [$this->stream];
        $write = null;
        $except = null;
        $ready = @stream_select($read, $write, $except, intdiv($timeoutMilliseconds, 1000), ($timeoutMilliseconds % 1000) * 1000);
        if ($ready === false) {
            throw new RuntimeException('Could not wait for filesystem changes.');
        }

        if ($ready === 0) {
            return [];
        }

        $records = @inotify_read($this->stream);
        if ($records === false) {
            throw new RuntimeException('Could not read filesystem changes.');
        }

        try {
            return $this->translateEvents($records);
        } catch (Throwable $error) {
            $this->close();
            throw $error;
        }
    }

    public function close(): void
    {
        if (is_resource($this->stream)) {
            fclose($this->stream);
        }

        $this->paths = [];
    }

    /** @return list<FileSystemEvent> */
    private function translateEvents(array $records): array
    {
        $events = [];
        $moves = [];
        $refresh = false;
        foreach ($records as $record) {
            $mask = $record['mask'];
            $watch = $record['wd'];
            if (($mask & IN_Q_OVERFLOW) !== 0) {
                throw new RuntimeException('The filesystem event queue overflowed. Rescan the directory and restart the watcher.');
            }

            if ($watch === $this->rootWatch && ($mask & (IN_DELETE_SELF | IN_MOVE_SELF | IN_IGNORED | IN_UNMOUNT)) !== 0) {
                throw new RuntimeException('The watched root directory is no longer available.');
            }

            $directory = $this->paths[$watch] ?? null;
            $name = $record['name'] ?? '';
            if ($directory === null || $name === '') {
                continue;
            }

            $path = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name;
            $isDirectory = ($mask & IN_ISDIR) !== 0;
            $cookie = $record['cookie'];
            if ($isDirectory && ($mask & (IN_CREATE | IN_DELETE | IN_MOVED_FROM | IN_MOVED_TO)) !== 0) {
                $refresh = true;
            }

            if (($mask & IN_MOVED_TO) !== 0 && isset($moves[$cookie])) {
                $previous = $moves[$cookie];
                $oldPath = $events[$previous]->path;
                unset($events[$previous], $moves[$cookie]);
                if ($isDirectory) {
                    $this->updateMovedPaths($oldPath, $path);
                }

                $events[] = new FileSystemEvent(FileSystemChangeType::Renamed, $path, $isDirectory, $oldPath);
                continue;
            }

            $type = match (true) {
                ($mask & (IN_CREATE | IN_MOVED_TO)) !== 0 => FileSystemChangeType::Created,
                ($mask & (IN_DELETE | IN_MOVED_FROM)) !== 0 => FileSystemChangeType::Deleted,
                default => FileSystemChangeType::Changed,
            };

            $events[] = new FileSystemEvent($type, $path, $isDirectory);
            if (($mask & IN_MOVED_FROM) !== 0 && $cookie !== 0) {
                $moves[$cookie] = array_key_last($events);
            }
        }

        if ($refresh && $this->includeSubdirectories) {
            $this->refreshWatches();
        }

        return array_values($events);
    }

    private function updateMovedPaths(string $oldPath, string $newPath): void
    {
        foreach ($this->paths as $watch => $path) {
            if ($path === $oldPath || str_starts_with($path, $oldPath . DIRECTORY_SEPARATOR)) {
                $this->paths[$watch] = $newPath . substr($path, strlen($oldPath));
            }
        }
    }

    private function refreshWatches(): void
    {
        clearstatcache();
        $paths = [$this->rootWatch => $this->root];
        try {
            $directories = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST,
            );
            foreach ($directories as $entry) {
                if ($entry->isDir() && !$entry->isLink()) {
                    $path = $entry->getPathname();
                    $watch = $this->addWatch($path);
                    if ($watch !== null) {
                        $paths[$watch] = $path;
                    }
                }
            }
        } catch (UnexpectedValueException $error) {
            throw new RuntimeException('Could not scan the watched directory tree.', 0, $error);
        }

        foreach (array_keys(array_diff_key($this->paths, $paths)) as $watch) {
            @inotify_rm_watch($this->stream, $watch);
        }

        $this->paths = $paths;
    }

    private function addWatch(string $path): ?int
    {
        $mask = IN_CREATE | IN_DELETE | IN_MODIFY | IN_ATTRIB | IN_MOVED_FROM | IN_MOVED_TO
            | IN_DELETE_SELF | IN_MOVE_SELF | IN_ONLYDIR | IN_DONT_FOLLOW;
        $watch = @inotify_add_watch($this->stream, $path, $mask);
        if ($watch !== false) {
            return $watch;
        }

        clearstatcache();
        if ($path !== $this->root && (!is_dir($path) || is_link($path))) {
            return null;
        }

        throw new RuntimeException("Could not watch directory: $path");
    }
}
