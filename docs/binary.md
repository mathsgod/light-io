# Binary readers and writers

`BinaryReader` and `BinaryWriter` accept a `Stream` or PHP resource. Read
values in the order and types used to write them. Closing either wrapper
closes the underlying stream.

```php
use Light\IO\BinaryReader;
use Light\IO\BinaryWriter;
use Light\IO\MemoryStream;

$stream = new MemoryStream();
try {
    $writer = new BinaryWriter($stream);
    $writer->writeUInt16(420);
    $writer->writeDecimal('0.10');
    $writer->writeString('Hello');

    $stream->seek(0);
    $reader = new BinaryReader($stream);
    $id = $reader->readUInt16();       // 420
    $amount = $reader->readDecimal();  // '0.10'
    $label = $reader->readString();   // 'Hello'
} finally {
    $stream->close();
}
```

| Method suffix | Format |
|---|---|
| `Byte`, `SByte`, `Int16`, `UInt16`, `Int32`, `UInt32`, `Int64`, `UInt64` | Fixed-width little-endian integers. `UInt64` uses decimal strings. `UInt32` and the 64-bit integer methods require 64-bit PHP. |
| `Half`, `Single`, `Double` | IEEE 754 little-endian floating-point values. `Half` rounds ties to even. |
| `Decimal` | .NET 16-byte decimal. Use decimal strings with a 96-bit coefficient and at most 28 fractional digits. |
| `Boolean` | One byte. Writers emit 0 or 1 and readers treat any nonzero byte as true. |
| `7BitEncodedInt`, `7BitEncodedInt64` | Variable-length signed integers using .NET's 7-bit format. The 64-bit variant requires 64-bit PHP. |
| `String` | Encoded text prefixed with its byte count as a 7-bit integer. |
| `Char`, `Chars` | Encoded code points without a length prefix. |
| `Bytes` | Raw bytes without a length prefix. |

Text uses UTF-8 by default. Select a [TextEncoding](text.md) when the format
requires another encoding. A supplementary character such as 😀 counts as
one code point here and occupies two .NET UTF-16 `char` units.
`readBytes()` and `readChars()` can return fewer items at EOF. Reading a
truncated scalar, character or length-prefixed string throws `RuntimeException`.
