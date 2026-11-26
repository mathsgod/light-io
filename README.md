# light-io

Light IO is a library for PHP 8.3 and later that provides text readers and writers inspired by Microsoft's `System.IO` namespace in .NET. Composer maps the `Light\IO` namespace to `src/` through PSR-4.

Both writers implement `TextWriter::write(string $text): void`. Each call writes the bytes you pass in, with no automatic newline or encoding conversion.

## Write to a string

```php
require 'vendor/autoload.php';

use Light\IO\StringWriter;

$writer = new StringWriter();
$writer->write('Hello');
$writer->writeLine(' world');

echo $writer->toString();
```

`writeLine()` appends the platform newline (`PHP_EOL`). Call it without text
to append just a newline. Casting the writer to a string returns the same
content as `toString()`.

## Write to a stream

```php
require 'vendor/autoload.php';

use Light\IO\StreamWriter;

$stream = fopen('php://temp', 'w+b');
try {
    $writer = new StreamWriter($stream);
    $writer->write('Hello');
    $writer->write(" world\n");

    rewind($stream);
    echo stream_get_contents($stream);
} finally {
    fclose($stream);
}
```

Pass an open, writable stream to `StreamWriter` and close it yourself when finished. The constructor throws `InvalidArgumentException` for invalid or read-only streams. A write that fails or stops making progress throws `RuntimeException`. Any bytes already written remain in the stream.

## Create a text file

```php
require 'vendor/autoload.php';

use Light\IO\File;

$writer = File::createText('hello.txt');
try {
    $writer->write('Hello');
    $writer->write(" world\n");
} finally {
    $writer->close();
}
```

`File::createText()` creates a new file or immediately truncates an existing one.
It returns a `StreamWriter` that writes the exact bytes supplied. Call `close()`
when finished to close the underlying stream. Calling `close()` again is safe.
An empty path or a path containing a null byte throws `InvalidArgumentException`.
If the file cannot be opened, `createText()` throws `RuntimeException`.

## Read from a string

```php
require 'vendor/autoload.php';

use Light\IO\StringReader;

$reader = new StringReader("first\nsecond\nthird");
$firstLine = $reader->readLine();     // 'first'
$remaining = $reader->readToEnd();   // "second\nthird"
```

`StringReader` implements `TextReader` for strings already in memory.
`readLine()` strips LF or CRLF and returns `null` at EOF.
`readToEnd()` returns the remaining bytes as supplied. Each read advances the position.

## Open a text file

```php
require 'vendor/autoload.php';

use Light\IO\File;

$reader = File::openText('hello.txt');
try {
    $firstLine = $reader->readLine();
    $remainingText = $reader->readToEnd();
} finally {
    $reader->close();
}
```

`File::openText()` opens an existing file for reading and throws `RuntimeException`
if it cannot open the file. You can also construct a `StreamReader` from an
already open, readable stream. Its `close()` method closes the underlying stream.

`StreamReader` implements `TextReader`. `readLine()` removes a trailing LF or CRLF,
returns an empty string for a blank line, and returns `null` at EOF.
`readToEnd()` returns the remaining bytes from the current position. Reads
preserve the supplied encoding and any byte order mark.

## Work with directories

```php
require 'vendor/autoload.php';

use Light\IO\Directory;

Directory::createDirectory('output/drafts');

foreach (Directory::enumerateFiles('output') as $path) {
    echo $path, PHP_EOL;
}

foreach (Directory::enumerateDirectories('output') as $path) {
    echo $path, PHP_EOL;
}

Directory::delete('output/drafts');
```

`Directory::exists()` checks whether a path points to a directory.
`createDirectory()` creates missing parent directories and accepts an existing directory.
The enumeration methods yield immediate child paths lazily and follow symbolic links
when determining an entry's type. `delete()` removes an empty directory.
Invalid paths throw `InvalidArgumentException`. Failed create, enumeration, and delete
operations throw `RuntimeException`. Enumeration errors surface during iteration.

## Inspect files and directories

```php
require 'vendor/autoload.php';

use Light\IO\DirectoryInfo;
use Light\IO\FileInfo;

$directory = new DirectoryInfo('output');
$directory->create();

$file = new FileInfo($directory->fullName() . DIRECTORY_SEPARATOR . 'hello.txt');
$writer = $file->createText();
try {
    $writer->write('Hello');
} finally {
    $writer->close();
}

foreach ($directory->enumerateFiles() as $entry) {
    echo $entry->name(), ' ', $entry->length(), ' bytes', PHP_EOL;
}
```

`FileInfo` and `DirectoryInfo` extend `FileSystemInfo`. Both can represent a local
path before it exists. Relative paths are resolved against the working directory
at construction. `fullName()` returns the absolute path without resolving symbolic links.
`exists()` checks the corresponding entry type and `lastWriteTime()` returns a Unix timestamp.

`FileInfo` provides `length()` in bytes, `openText()`, `createText()`, and `delete()`.
`DirectoryInfo` provides `create()`, lazy enumeration of immediate children as
`FileInfo` or `DirectoryInfo` objects, and `delete()` for empty directories.
Failed operations, including reading metadata for a missing entry, throw `RuntimeException`.

`DirectoryInfo::getDirectories()` returns a list of immediate child directories
by default. Pass a name pattern with `*` or `?` and use
`SearchOption::AllDirectories` to search nested directories.

```php
use Light\IO\DirectoryInfo;
use Light\IO\SearchOption;

$directory = new DirectoryInfo('output');
$drafts = $directory->getDirectories('draft*', SearchOption::AllDirectories);
```

`enumerateDirectories()` accepts the same arguments and yields results lazily.
Matching directory symlinks can appear in the results. Recursive searches skip
their contents.

## Check disk space

```php
require 'vendor/autoload.php';

use Light\IO\DriveInfo;

$drive = new DriveInfo(__DIR__);
echo $drive->getTotalSize(), ' bytes total', PHP_EOL;
echo $drive->getAvailableFreeSpace(), ' bytes available', PHP_EOL;
```

Pass an existing local directory, such as `/var/log` on Linux or `C:\` on
Windows. `DriveInfo` resolves the directory at construction and reports space
for the filesystem that contains it. Both methods query the filesystem on each
call and return bytes as a `float`, matching PHP's disk-space functions.
Empty paths and paths containing null bytes throw `InvalidArgumentException`.
An unresolvable directory or a failed space query throws `RuntimeException`.

## Write binary data

```php
require 'vendor/autoload.php';

use Light\IO\BinaryWriter;

$stream = fopen('php://temp', 'w+b');
$writer = new BinaryWriter($stream);
try {
    $writer->writeInt32(42);
    $writer->writeBoolean(true);
    $writer->writeString('Hi');
    rewind($stream);
    echo bin2hex(stream_get_contents($stream)), PHP_EOL;
} finally {
    $writer->close();
}
```

The example outputs `2a00000001024869`. `writeInt32()` writes a signed
little-endian integer, `writeBoolean()` writes one byte, and `writeString()`
writes UTF-8 bytes preceded by their byte count as a 7-bit encoded integer.
Use `writeBytes()` for raw bytes without a length prefix. Open file streams in
binary mode. `close()` closes the underlying stream.

## Read binary data

```php
require 'vendor/autoload.php';

use Light\IO\BinaryReader;

$stream = fopen('php://temp', 'w+b');
fwrite($stream, hex2bin('2a00000001024869'));
rewind($stream);

$reader = new BinaryReader($stream);
try {
    $number = $reader->readInt32();  // 42
    $flag = $reader->readBoolean();  // true
    $text = $reader->readString();   // 'Hi'
} finally {
    $reader->close();
}
```

Read values in the same order and types used to write them. The typed methods
throw `RuntimeException` if a value is truncated or its string data is invalid.
`readBytes($count)` returns up to the requested number of raw bytes and may
return fewer at end of stream. A negative count throws `InvalidArgumentException`.
Open file streams in binary mode. `close()` closes the underlying stream.

The binary reader and writer also support `Byte`, `SByte`, `Int16`, `UInt16`,
`UInt32`, `Int64`, `UInt64`, `Single`, and `Double` through corresponding
`read*()` and `write*()` methods. Multi-byte numbers use little-endian byte
order. `UInt64` values use decimal strings such as `'18446744073709551615'`.
`UInt32` and all 64-bit integer methods require 64-bit PHP.
`read7BitEncodedInt()` and `write7BitEncodedInt()` handle signed 32-bit
values, with `Int64` variants for signed 64-bit values.

## Work with streams

```php
require 'vendor/autoload.php';

use Light\IO\BinaryReader;
use Light\IO\BinaryWriter;
use Light\IO\Stream;

$stream = Stream::fromResource(fopen('php://temp', 'w+b'));
try {
    $writer = new BinaryWriter($stream);
    $writer->writeInt32(42);
    $stream->flush();
    $stream->seek(0);

    $reader = new BinaryReader($stream);
    echo $reader->readInt32(), PHP_EOL;
} finally {
    $stream->close();
}
```

Extend the abstract `Stream` class to provide a different byte source or
destination. `Stream::fromResource()` adapts a PHP stream resource and exposes
read, write, seek, position, and flush operations. Use `canRead()`, `canWrite()`,
and `canSeek()` to check capabilities. `StreamReader`, `StreamWriter`,
`BinaryReader`, and `BinaryWriter` accept either a `Stream` or a PHP stream
resource. Closing any of them closes its underlying stream.

## Buffer stream reads and writes

```php
require 'vendor/autoload.php';

use Light\IO\BufferedStream;

$stream = new BufferedStream(fopen('php://temp', 'w+b'));
try {
    $stream->write('Hello');
    $stream->write(' world');
    $stream->seek(0);
    echo $stream->readToEnd(), PHP_EOL;
} finally {
    $stream->close();
}
```

`BufferedStream` accepts a `Stream` or PHP stream resource and defaults to a
4096-byte buffer. It batches small writes and reads ahead for small reads.
`flush()`, `seek()`, and `close()` write any pending bytes. `close()` also
closes the underlying stream. `tell()` accounts for bytes held in the buffer.
Switching from reading to writing with unread buffered bytes requires a
seekable stream. Pass `BufferedStream` directly to a reader or writer.

## Read bytes already in memory

```php
require 'vendor/autoload.php';

use Light\IO\BinaryReader;
use Light\IO\ReadOnlyMemoryStream;

$reader = new BinaryReader(new ReadOnlyMemoryStream("\x2A\x00\x00\x00\x02Hi"));
try {
    echo $reader->readInt32(), ' ', $reader->readString(), PHP_EOL;
} finally {
    $reader->close();
}
```

`ReadOnlyMemoryStream` takes a PHP string and starts reading at byte offset zero.
It supports `seek()` and `tell()`. Reads at or beyond the end return an empty
string, and writes throw `RuntimeException`.

## Read, write, and move files

```php
use Light\IO\File;
use Light\IO\FileAccess;
use Light\IO\FileMode;

File::writeAllLines('notes.txt', ['First line', 'Second line']);
File::appendAllText('notes.txt', 'Last line');
foreach (File::readLines('notes.txt') as $line) {
    echo $line, PHP_EOL;
}

File::copy('notes.txt', 'notes.bak', overwrite: true);
$stream = File::open('notes.bak', FileMode::Open, FileAccess::Read);
try {
    echo $stream->readToEnd();
} finally {
    $stream->close();
}
```

`readAllText()`, `writeAllText()` and the byte methods use PHP strings as-is.
The line methods read LF and CRLF and write the platform newline. `readLines()`
opens the file when iteration starts. `copy()` and `move()` default to refusing
an existing destination. Moving without overwrite requires source and
destination on a filesystem that supports hard links between those paths.
`delete()` accepts a missing file, and `exists()` checks for a regular file.

`create()` resets the file and opens it for reading and writing. `openRead()`
opens an existing file for reading, and `openWrite()` opens or creates a file
for writing while preserving existing bytes. `File::open()` also supports
exclusive creation, truncating an existing file, and append mode. Opening or
truncating an existing file with explicit write-only access requires OS read
and write permissions because PHP opens it in `r+b` mode internally.

`File::open()`, `create()`, `openRead()`, and `openWrite()` return a
`FileStream`. You can also construct one directly with
`new FileStream($path, FileMode::Open, FileAccess::ReadWrite)`. Use `length()`
to get the file size in bytes and `setLength($bytes)` to resize a writable
file. Resizing preserves the current byte position, even if the file shrinks
past that position.

## Update a fixed-size buffer in memory

```php
use Light\IO\BinaryWriter;
use Light\IO\WritableMemoryStream;

$buffer = str_repeat("\0", 6);
$stream = new WritableMemoryStream($buffer);
$writer = new BinaryWriter($stream);
try {
    $stream->seek(4);
    $writer->writeBytes('Hi');
    $stream->seek(0);
    $writer->writeInt32(2);
} finally {
    $writer->close();
}

echo bin2hex($buffer), PHP_EOL; // 020000004869
```

Pass a PHP string variable by reference. Its initial byte count is both the
stream length and the fixed capacity. Writes update the original variable.
`setLength()` changes the readable length within that capacity and zero-fills
newly exposed bytes. Avoid resizing the backing string while the stream is open.
Closing the stream releases the reference and leaves the bytes in the variable.

## Write to an expandable stream in memory

```php
use Light\IO\BinaryWriter;
use Light\IO\MemoryStream;

$stream = new MemoryStream();
$writer = new BinaryWriter($stream);
try {
    $writer->writeInt32(42);
    $writer->writeString('Hi');
} finally {
    $writer->close();
}

echo bin2hex($stream->toString()), PHP_EOL; // 2a000000024869
```

`MemoryStream` starts empty or takes an optional string of initial bytes.
Writes start at byte offset zero and automatically grow the stream as needed.
Use `seek()` to change position and `setLength()` to resize the stream. Writes
beyond the end and length increases fill new gaps with zero bytes. `toString()`
returns the entire buffer, independent of position, even after `close()`.

## Watch a directory for changes

```php
use Light\IO\FileSystemWatcher;

$watcher = new FileSystemWatcher('uploads', includeSubdirectories: true);
try {
    foreach ($watcher->readEvents(1000) as $event) {
        echo $event->type->name, ' ', $event->path, PHP_EOL;
    }
} finally {
    $watcher->close();
}
```

`FileSystemWatcher` uses the optional Linux `ext-inotify` extension. A worker
can call `readEvents()` repeatedly. The timeout is in milliseconds and defaults
to zero for a non-blocking check. Events report creation, modification, deletion,
and renames. Paired renames include `oldPath`. Rename halves reported in
different batches can appear as separate creation and deletion events.

Recursive monitoring skips directory symlinks and adds new directories when
events are read. Scan a new directory if you also need to find entries created
before its watch was registered. Queue overflow or loss of the watched root
closes the watcher and throws `RuntimeException`.

## Run tests

```sh
composer install
composer test
```
