<?php

declare(strict_types=1);

namespace Light\IO;

use InvalidArgumentException;
use RuntimeException;

final class Path
{
    private function __construct()
    {
    }

    public static function combine(string ...$paths): string
    {
        $result = '';
        foreach ($paths as $path) {
            self::validate($path);
            if ($path === '') {
                continue;
            }

            if ($result === '' || self::root($path) !== '') {
                $result = $path;
            } else {
                $separator = strpbrk(substr($result, -1), self::separators()) !== false
                    || (DIRECTORY_SEPARATOR === '\\' && preg_match('~^[a-z]:$~iD', $result) === 1)
                    ? '' : DIRECTORY_SEPARATOR;
                $result .= $separator . $path;
            }
        }

        return $result;
    }

    public static function getFileName(string $path): string
    {
        self::validate($path);
        $remainder = substr($path, strlen(self::root($path)));
        $offset = strrpos(self::normalizeSeparators($remainder), DIRECTORY_SEPARATOR);

        return $offset === false ? $remainder : substr($remainder, $offset + 1);
    }

    public static function getExtension(string $path): string
    {
        $name = self::getFileName($path);
        $dot = strrpos($name, '.');

        return $dot === false || $dot === strlen($name) - 1 ? '' : substr($name, $dot);
    }

    public static function changeExtension(string $path, ?string $extension): string
    {
        self::validate($path);
        if ($extension !== null) {
            self::validate($extension);
        }

        if ($path === '') {
            return '';
        }

        $name = self::getFileName($path);
        $dot = strrpos($name, '.');
        if ($dot !== false) {
            $path = substr($path, 0, strlen($path) - strlen($name) + $dot);
        }

        if ($extension === null) {
            return $path;
        }

        return $path . (str_starts_with($extension, '.') ? '' : '.') . $extension;
    }

    public static function isPathRooted(string $path): bool
    {
        self::validate($path);

        return self::root($path) !== '';
    }

    public static function getFullPath(string $path, ?string $basePath = null): string
    {
        self::validate($path);
        if ($path === '') {
            throw new InvalidArgumentException('Expected a non-empty path.');
        }

        if ($basePath !== null) {
            self::validate($basePath);
            if (!self::isFullyQualified(self::root($basePath))) {
                throw new InvalidArgumentException('Expected an absolute base path.');
            }
        }

        $path = self::normalizeSeparators($path);
        $root = self::root($path);
        if (!self::isFullyQualified($root)) {
            if ($basePath === null) {
                $basePath = getcwd();
                if ($basePath === false) {
                    throw new RuntimeException('Could not determine the current directory.');
                }
            }

            $basePath = self::normalizeSeparators($basePath);
            $baseRoot = self::root($basePath);
            if ($root === '') {
                $path = self::combine($basePath, $path);
            } elseif ($root === DIRECTORY_SEPARATOR) {
                $path = rtrim($baseRoot, DIRECTORY_SEPARATOR) . $path;
            } elseif (str_ends_with($root, ':') && strncasecmp($root, $baseRoot, 2) === 0) {
                $path = self::combine($basePath, substr($path, 2));
            } else {
                throw new InvalidArgumentException('Could not resolve the relative drive path against the base path.');
            }

            $root = self::root($path);
        }

        $parts = self::components($path, $root);
        $normalized = $root . implode(DIRECTORY_SEPARATOR, $parts);
        if (str_ends_with($path, DIRECTORY_SEPARATOR) && !str_ends_with($normalized, DIRECTORY_SEPARATOR)) {
            $normalized .= DIRECTORY_SEPARATOR;
        }

        return $normalized;
    }

    public static function getRelativePath(string $relativeTo, string $path): string
    {
        $from = self::getFullPath($relativeTo);
        $to = self::getFullPath($path);
        $fromRoot = self::root($from);
        $toRoot = self::root($to);
        if (!self::sameComponent(rtrim($fromRoot, DIRECTORY_SEPARATOR), rtrim($toRoot, DIRECTORY_SEPARATOR))) {
            return $to;
        }

        $fromParts = self::components($from, $fromRoot);
        $toParts = self::components($to, $toRoot);
        $shared = 0;
        while (isset($fromParts[$shared], $toParts[$shared])
            && self::sameComponent($fromParts[$shared], $toParts[$shared])
        ) {
            $shared++;
        }

        $parts = array_merge(array_fill(0, count($fromParts) - $shared, '..'), array_slice($toParts, $shared));
        if ($parts === []) {
            return '.';
        }

        $result = implode(DIRECTORY_SEPARATOR, $parts);

        return str_ends_with($to, DIRECTORY_SEPARATOR) ? $result . DIRECTORY_SEPARATOR : $result;
    }

    /** @return list<string> */
    private static function components(string $path, string $root): array
    {
        $parts = [];
        foreach (explode(DIRECTORY_SEPARATOR, substr($path, strlen($root))) as $part) {
            if ($part === '..') {
                array_pop($parts);
            } elseif ($part !== '' && $part !== '.') {
                $parts[] = $part;
            }
        }

        return $parts;
    }

    private static function root(string $path): string
    {
        $normalized = self::normalizeSeparators($path);
        if (DIRECTORY_SEPARATOR === '\\') {
            $slashPath = str_replace('\\', '/', $normalized);
            if (preg_match('~^//[^/]+/[^/]+/?~', $slashPath, $match) === 1
                || preg_match('~^[a-z]:/?~i', $slashPath, $match) === 1
            ) {
                return substr($path, 0, strlen($match[0]));
            }
        }

        return str_starts_with($normalized, DIRECTORY_SEPARATOR) ? $path[0] : '';
    }

    private static function isFullyQualified(string $root): bool
    {
        return DIRECTORY_SEPARATOR === '/'
            ? $root === '/'
            : strlen($root) > 2;
    }

    private static function normalizeSeparators(string $path): string
    {
        return DIRECTORY_SEPARATOR === '\\' ? str_replace('/', '\\', $path) : $path;
    }

    private static function separators(): string
    {
        return DIRECTORY_SEPARATOR === '\\' ? '\\/' : '/';
    }

    private static function sameComponent(string $left, string $right): bool
    {
        return DIRECTORY_SEPARATOR === '\\' ? strcasecmp($left, $right) === 0 : $left === $right;
    }

    private static function validate(string $path): void
    {
        if (str_contains($path, "\0")) {
            throw new InvalidArgumentException('Expected a path without null bytes.');
        }
    }
}
