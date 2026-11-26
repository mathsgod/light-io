# Files

## File and FileInfo

| Task | Methods on `File` |
|---|---|
| Check or remove an entry | `exists()` checks for a regular file. `delete()` accepts a missing file. |
| Copy or move | `copy()` and `move()` refuse an existing destination by default. Set `overwrite: true` to replace it. Moving without overwrite requires hard-link support between the paths. |
| Read a whole file | `readAllBytes()`, `readAllText()`, `readAllLines()` |
| Read incrementally | `readLines()` yields lines and opens the file when iteration begins. |
| Replace content | `writeAllBytes()`, `writeAllText()`, `writeAllLines()` |
| Append content | `appendAllText()`, `appendAllLines()` |
| Open a text wrapper | `openText()`, `createText()`, `appendText()` |

The whole-file text methods preserve raw bytes. Line readers accept LF and
CRLF. Line writers use `PHP_EOL`. For encoding and BOM options on text streams,
see [Text I/O](text.md).

`FileInfo` represents a path and exposes `exists()`, `length()`, `openText()`,
`createText()`, and `delete()`. It also inherits path and timestamp methods
from [FileSystemInfo](directories.md).

## FileMode, FileAccess and FileShare

`File::open()`, `create()`, `openRead()`, and `openWrite()` return a
[FileStream](streams.md). You can also construct `FileStream` directly with
the same arguments as `File::open()`.

| `FileMode` | Effect |
|---|---|
| `CreateNew` | Create a file exclusively. |
| `Create` | Create a file or clear an existing file. |
| `Open` | Open an existing file. |
| `OpenOrCreate` | Open a file or create it, preserving existing bytes. |
| `Truncate` | Clear an existing file. |
| `Append` | Open or create a file and direct writes to the end. |

`FileAccess` has `Read`, `Write`, and `ReadWrite` cases. The default is
`ReadWrite` except for `Append`, which requires `Write`. Explicit write-only
`Open` and `Truncate` use PHP's `r+b` mode internally and require OS read
and write permissions.

Sharing is opt-in. `FileShare::Read` takes a shared `flock()` on a read-only
stream. `FileShare::None` takes an exclusive lock. Participants must request
locks for coordination. Conflicts fail immediately and closing releases the
lock. `Write`, `ReadWrite`, `Delete`, and other flag combinations are unsupported.

```php
use Light\IO\File;
use Light\IO\FileAccess;
use Light\IO\FileMode;
use Light\IO\FileShare;

$stream = File::open('report.csv', FileMode::Open, FileAccess::Read, FileShare::Read);
try {
    echo $stream->readLine();
} finally {
    $stream->close();
}
```

## FileAttributes and UnixFileMode

On Unix-like systems `File::getAttributes()` reports read-only mode, dotfile
visibility, directories and symbolic links using `FileAttributes` flags.
`setAttributes()` can toggle `ReadOnly` on non-symlinks. Setting it removes
all write bits. Clearing it adds owner write permission.

`File::getUnixFileMode()` returns the permission and special mode bits.
`setUnixFileMode()` accepts octal values such as `0640` or flags from
`UnixFileMode` combined with `|`. These methods follow symbolic links.

```php
use Light\IO\File;
use Light\IO\UnixFileMode;

File::setUnixFileMode('notes.txt', UnixFileMode::UserRead | UnixFileMode::UserWrite | UnixFileMode::GroupRead);
printf("%04o\n", File::getUnixFileMode('notes.txt')); // 0640
```
