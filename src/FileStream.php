<?php

declare(strict_types=1);

namespace Light\IO;

use InvalidArgumentException;
use RuntimeException;

final class FileStream extends ResourceStream
{
    public function __construct(
        string $path,
        FileMode $mode,
        ?FileAccess $access = null,
        ?int $share = null,
    ) {
        if ($path === '' || str_contains($path, "\0")) {
            throw new InvalidArgumentException('Expected a non-empty file path without null bytes.');
        }

        $access ??= $mode === FileMode::Append ? FileAccess::Write : FileAccess::ReadWrite;
        if (($mode === FileMode::Append && $access !== FileAccess::Write)
            || ($mode !== FileMode::Open && $access === FileAccess::Read)
        ) {
            throw new InvalidArgumentException('The file mode is incompatible with the requested access.');
        }

        if (is_dir($path)) {
            throw new RuntimeException('Expected a file path, got a directory.');
        }

        $lock = match ($share) {
            null => null,
            FileShare::None => LOCK_EX,
            FileShare::Read => $access === FileAccess::Read ? LOCK_SH : throw new InvalidArgumentException(
                'Shared read locking requires read-only access.',
            ),
            default => throw new InvalidArgumentException('This stream supports FileShare::None and read-only FileShare::Read.'),
        };

        $phpMode = match ($mode) {
            FileMode::CreateNew => $access === FileAccess::Write ? 'xb' : 'x+b',
            FileMode::Create => match (true) {
                $lock === null => $access === FileAccess::Write ? 'wb' : 'w+b',
                default => $access === FileAccess::Write ? 'cb' : 'c+b',
            },
            FileMode::Open, FileMode::Truncate => $access === FileAccess::Read ? 'rb' : 'r+b',
            FileMode::OpenOrCreate => $access === FileAccess::Write ? 'cb' : 'c+b',
            FileMode::Append => 'ab',
        };

        $resource = @fopen($path, $phpMode);
        if ($resource === false) {
            throw new RuntimeException('Could not open the file.');
        }

        if ($lock !== null && !@flock($resource, $lock | LOCK_NB)) {
            fclose($resource);
            throw new RuntimeException('Could not acquire the requested file sharing lock.');
        }

        $truncate = $mode === FileMode::Truncate || ($mode === FileMode::Create && $lock !== null);
        if (($truncate && !@ftruncate($resource, 0))
            || ($mode === FileMode::Append && @fseek($resource, 0, SEEK_END) !== 0)
        ) {
            fclose($resource);
            throw new RuntimeException('Could not initialize the file stream.');
        }

        parent::__construct($resource, $access);
    }

    public function length(): int
    {
        $this->ensureOpen();
        $stat = @fstat($this->resource);
        if ($stat === false) {
            throw new RuntimeException('Could not determine the file length.');
        }

        return $stat['size'];
    }

    public function setLength(int $bytes): void
    {
        if ($bytes < 0) {
            throw new InvalidArgumentException('Expected a non-negative file length.');
        }

        $this->ensureOpen();
        if (!$this->canWrite()) {
            throw new RuntimeException('The file stream is not writable.');
        }

        $position = $this->tell();
        if (!@ftruncate($this->resource, $bytes)) {
            throw new RuntimeException('Could not set the file length.');
        }

        // Seeking from the end discards PHP's read buffer after the resize.
        $this->seek(0, SEEK_END);
        $this->seek($position);
    }
}
