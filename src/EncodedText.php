<?php

declare(strict_types=1);

namespace Light\IO;

use InvalidArgumentException;
use RuntimeException;

/** @internal */
final class EncodedText
{
    public static function encode(string $text, TextEncoding $encoding): string
    {
        if (preg_match('//u', $text) !== 1) {
            throw new InvalidArgumentException('Expected a valid UTF-8 string.');
        }

        return self::convert($text, TextEncoding::Utf8, $encoding);
    }

    public static function decode(string $bytes, TextEncoding $encoding): string
    {
        $text = self::convert($bytes, $encoding, TextEncoding::Utf8);
        if (preg_match('//u', $text) !== 1) {
            throw new RuntimeException('The stream contains an invalid UTF-8 string.');
        }

        return $text;
    }

    /** @param callable(int): string $readBytes */
    public static function readChar(callable $readBytes, TextEncoding $encoding): ?string
    {
        $width = match ($encoding) {
            TextEncoding::Utf8 => 1,
            TextEncoding::Utf16LE, TextEncoding::Utf16BE => 2,
            TextEncoding::Utf32LE, TextEncoding::Utf32BE => 4,
        };
        $bytes = $readBytes($width);
        if ($bytes === '') {
            return null;
        }

        if (strlen($bytes) !== $width) {
            throw new RuntimeException('Unexpected end of stream in a character.');
        }

        $extra = 0;
        if ($encoding === TextEncoding::Utf8) {
            $first = ord($bytes);
            $extra = match (true) {
                $first < 0x80 => 0,
                $first >= 0xC2 && $first <= 0xDF => 1,
                $first >= 0xE0 && $first <= 0xEF => 2,
                $first >= 0xF0 && $first <= 0xF4 => 3,
                default => throw new RuntimeException('The stream contains an invalid UTF-8 character.'),
            };
        } elseif ($width === 2) {
            $unit = unpack($encoding === TextEncoding::Utf16LE ? 'v' : 'n', $bytes)[1];
            $extra = $unit >= 0xD800 && $unit <= 0xDBFF ? 2 : 0;
        }

        if ($extra !== 0) {
            $remaining = $readBytes($extra);
            if (strlen($remaining) !== $extra) {
                throw new RuntimeException('Unexpected end of stream in a character.');
            }

            $bytes .= $remaining;
        }

        return self::decode($bytes, $encoding);
    }

    public static function requireSupport(TextEncoding $encoding): void
    {
        if ($encoding !== TextEncoding::Utf8 && !extension_loaded('iconv')) {
            throw new RuntimeException('UTF-16 and UTF-32 encodings require the PHP iconv extension.');
        }
    }

    public static function validateChar(string $character): void
    {
        if (preg_match('/\A.\z/us', $character) !== 1) {
            throw new InvalidArgumentException('Expected exactly one Unicode code point.');
        }
    }

    private static function convert(string $bytes, TextEncoding $from, TextEncoding $to): string
    {
        if ($from === $to) {
            return $bytes;
        }

        self::requireSupport($from);
        self::requireSupport($to);
        $converted = @iconv($from->value, $to->value, $bytes);
        if ($converted === false) {
            throw new RuntimeException('The bytes are invalid for the selected text encoding.');
        }

        return $converted;
    }
}
