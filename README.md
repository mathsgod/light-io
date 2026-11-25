# light-io

Text writers for PHP 8.3 and later. Composer maps the `Light\IO` namespace to `src/` through PSR-4.

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

## Run tests

```sh
composer install
composer test
```
