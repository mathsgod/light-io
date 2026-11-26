# Streams

## Stream and ResourceStream

`Stream` is the abstract byte-stream API. Use `canRead()`, `canWrite()` and
`canSeek()` to check capabilities. `read($count)` can return a short chunk.
`readExactly($count)` collects the full count or throws `RuntimeException` at
early EOF, after consuming any bytes read. `seek()` takes a byte offset and a
PHP seek origin (`SEEK_SET`, `SEEK_CUR`, or `SEEK_END`). `tell()` returns the
current byte offset. `copyTo()` copies from the current positions, leaving both
streams open. Flush a buffered destination when the data needs to be visible.

`ResourceStream` adapts an open PHP stream resource. Use
`Stream::fromResource($resource)` to wrap it. Closing the wrapper closes the
resource.

## FileStream and BufferedStream

`FileStream` opens a file using a mode and access setting. `length()` reports
the file size. `setLength()` resizes writable files and preserves the current
position. See [Files](files.md) for mode and sharing options.

`BufferedStream` accepts a `Stream` or resource and defaults to a 4096-byte
buffer. It reads ahead and batches small writes. `flush()`, `seek()` and
`close()` drain pending writes. Switching to writing while read-ahead bytes
remain requires a seekable underlying stream. Closing the wrapper also closes
its underlying stream.

```php
use Light\IO\BufferedStream;
use Light\IO\File;

$source = new BufferedStream(File::openRead('source.bin'));
try {
    $destination = File::create('destination.bin');
    try {
        $source->copyTo($destination);
        $destination->flush();
    } finally {
        $destination->close();
    }
} finally {
    $source->close();
}
```

## Memory streams

- `MemoryStream` grows as needed and accepts an optional initial string.
  `toString()` returns all bytes even after closing. Use `setLength()` to
  truncate or extend the stream. Extensions and gaps are zero-filled.
- `ReadOnlyMemoryStream` reads an existing PHP string and supports seeking.
  Writes throw `RuntimeException`.
- `WritableMemoryStream` takes a string variable by reference. Its original
  byte length is the fixed capacity. Writes update that variable. Keep its
  size consistent while attached. `setLength()` adjusts the readable length
  within capacity, and `close()` releases the reference.

```php
use Light\IO\WritableMemoryStream;

$buffer = 'header:____';
$stream = new WritableMemoryStream($buffer);
try {
    $stream->seek(7);
    $stream->write('1234');
} finally {
    $stream->close();
}

echo $buffer; // header:1234
```
