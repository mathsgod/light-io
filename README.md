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

## Run tests

```sh
composer install
composer test
```
