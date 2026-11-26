# Text readers and writers

## Reader and writer interfaces

`TextReader` defines `readLine()` and `readToEnd()`. A line ends at LF or CRLF
and is returned without the terminator. `readLine()` returns `null` at EOF
and an empty string for an empty line. `TextWriter` defines `write()`.

## StringReader and StringWriter

Use `StringReader` to read lines or the remaining bytes of a PHP string.
`StringWriter` builds a PHP string in memory. `writeLine()` appends
`PHP_EOL` and `toString()` returns the accumulated contents.

```php
use Light\IO\StringReader;
use Light\IO\StringWriter;

$reader = new StringReader("first\nsecond");
$writer = new StringWriter();
while (($line = $reader->readLine()) !== null) {
    $writer->writeLine(strtoupper($line));
}
echo $writer->toString();
```

## StreamReader, StreamWriter and TextEncoding

These classes wrap a `Stream` or PHP resource. Use `close()` to close the
underlying stream when finished.

`TextEncoding` offers `Utf8`, `Utf16LE`, `Utf16BE`, `Utf32LE`, and
`Utf32BE`. UTF-16 and UTF-32 require `ext-iconv`. With an explicit encoding,
the writer accepts UTF-8 text and the reader returns UTF-8 text. By default
the line, whole-stream and write methods preserve the original bytes.

`StreamReader::readChar()` and `readChars()` count Unicode code points.
Without an encoding they interpret input as UTF-8. The single-character
method returns `null` at EOF. `StreamWriter::writeChar()` writes one code
point. Writers leave BOM emission to the caller.

`detectBom: true` makes the reader check the start of the input for a UTF-8,
UTF-16 or UTF-32 BOM. A recognized BOM is stripped and overrides the fallback
encoding. Without a BOM the reader uses the selected encoding or UTF-8.

```php
use Light\IO\File;
use Light\IO\TextEncoding;

$reader = File::openText('input.txt', TextEncoding::Utf8, detectBom: true);
try {
    echo $reader->readChars(10);
} finally {
    $reader->close();
}
```
