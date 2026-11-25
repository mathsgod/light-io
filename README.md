# light-io

Text readers and writers for PHP 8.3 and later. Composer maps the `Light\IO` namespace to `src/` through PSR-4.

Both writers implement `TextWriter::write(string $text): void`. Each call writes the bytes you pass in, with no automatic newline or encoding conversion.

## Write to a string

```php
require 'vendor/autoload.php';

use Light\IO\StringWriter;

$writer = new StringWriter();
$writer->write('Hello');
$writer->write(' world');

echo $writer->toString(); // Hello world
```

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

## Run tests

```sh
composer install
composer test
```
