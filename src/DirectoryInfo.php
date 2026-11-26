<?php

declare(strict_types=1);

namespace Light\IO;

use InvalidArgumentException;

final class DirectoryInfo extends FileSystemInfo
{
    public function exists(): bool
    {
        clearstatcache();

        return Directory::exists($this->fullName());
    }

    public function create(): void
    {
        Directory::createDirectory($this->fullName());
    }

    /** @return iterable<FileInfo> */
    public function enumerateFiles(): iterable
    {
        foreach (Directory::enumerateFiles($this->fullName()) as $path) {
            yield new FileInfo($path);
        }
    }

    /** @return list<DirectoryInfo> */
    public function getDirectories(
        string $searchPattern = '*',
        SearchOption $searchOption = SearchOption::TopDirectoryOnly,
    ): array {
        return iterator_to_array($this->enumerateDirectories($searchPattern, $searchOption), false);
    }

    /** @return iterable<DirectoryInfo> */
    public function enumerateDirectories(
        string $searchPattern = '*',
        SearchOption $searchOption = SearchOption::TopDirectoryOnly,
    ): iterable {
        if ($searchPattern === '' || str_contains($searchPattern, "\0") || str_contains($searchPattern, '/')
            || (DIRECTORY_SEPARATOR === '\\' && str_contains($searchPattern, '\\'))
        ) {
            throw new InvalidArgumentException('Expected a non-empty directory name pattern.');
        }

        $pattern = strtr(preg_quote($searchPattern, '~'), ['\\*' => '.*', '\\?' => '.']);
        $modifiers = DIRECTORY_SEPARATOR === '\\' ? 'si' : 's';

        yield from self::findDirectories($this->fullName(), '~\A' . $pattern . '\z~' . $modifiers, $searchOption);
    }

    /** @return iterable<DirectoryInfo> */
    private static function findDirectories(string $directory, string $pattern, SearchOption $searchOption): iterable
    {
        foreach (Directory::enumerateDirectories($directory) as $path) {
            if (preg_match($pattern, basename($path)) === 1) {
                yield new self($path);
            }

            if ($searchOption === SearchOption::AllDirectories && !is_link($path)) {
                yield from self::findDirectories($path, $pattern, $searchOption);
            }
        }
    }

    public function delete(): void
    {
        Directory::delete($this->fullName());
    }
}
