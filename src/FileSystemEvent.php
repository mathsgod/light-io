<?php

declare(strict_types=1);

namespace Light\IO;

final readonly class FileSystemEvent
{
    public function __construct(
        public FileSystemChangeType $type,
        public string $path,
        public bool $isDirectory,
        public ?string $oldPath = null,
    ) {
    }
}
