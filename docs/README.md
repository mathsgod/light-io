# Light IO documentation

Install with `composer require mathsgod/light-io` in a Composer project. Load
`vendor/autoload.php` before running the examples. Public types use the
`Light\IO` namespace. The library provides synchronous I/O.

| Guide | Types |
|---|---|
| [Streams](streams.md) | `Stream`, `ResourceStream`, `FileStream`, `BufferedStream`, `MemoryStream`, `ReadOnlyMemoryStream`, `WritableMemoryStream` |
| [Text I/O](text.md) | `TextReader`, `TextWriter`, `StreamReader`, `StreamWriter`, `StringReader`, `StringWriter`, `TextEncoding` |
| [Binary I/O](binary.md) | `BinaryReader`, `BinaryWriter` |
| [Files](files.md) | `File`, `FileInfo`, `FileMode`, `FileAccess`, `FileShare`, `FileAttributes`, `UnixFileMode` |
| [Directories and drives](directories.md) | `Directory`, `DirectoryInfo`, `FileSystemInfo`, `SearchOption`, `DriveInfo` |
| [Paths](paths.md) | `Path` |
| [Filesystem monitoring](watching.md) | `FileSystemWatcher`, `FileSystemEvent`, `FileSystemChangeType` |
