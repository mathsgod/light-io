<?php

declare(strict_types=1);

namespace Light\IO;

use InvalidArgumentException;
use RuntimeException;

final class BinaryWriter
{
    private readonly StreamWriter $writer;

    /** @param Stream|resource $stream */
    public function __construct(mixed $stream, private readonly TextEncoding $encoding = TextEncoding::Utf8)
    {
        EncodedText::requireSupport($encoding);
        $this->writer = new StreamWriter($stream);
    }

    public function writeInt32(int $value): void
    {
        $this->writeBytes(pack('V', self::inRange($value, -2147483647 - 1, 2147483647)));
    }

    public function writeByte(int $value): void
    {
        $this->writeBytes(chr(self::inRange($value, 0, 255)));
    }

    public function writeSByte(int $value): void
    {
        $this->writeBytes(pack('c', self::inRange($value, -128, 127)));
    }

    public function writeInt16(int $value): void
    {
        $this->writeBytes(pack('v', self::inRange($value, -32768, 32767)));
    }

    public function writeUInt16(int $value): void
    {
        $this->writeBytes(pack('v', self::inRange($value, 0, 65535)));
    }

    public function writeUInt32(int $value): void
    {
        self::require64Bit();
        $this->writeBytes(pack('V', self::inRange($value, 0, 4294967295)));
    }

    public function writeInt64(int $value): void
    {
        self::require64Bit();
        $this->writeBytes(pack('P', $value));
    }

    public function writeUInt64(string $value): void
    {
        self::require64Bit();
        if (preg_match('/\A[0-9]+\z/', $value) !== 1) {
            throw new InvalidArgumentException('Expected an unsigned decimal integer.');
        }

        $value = ltrim($value, '0');
        if (strlen($value) > 20 || (strlen($value) === 20 && strcmp($value, '18446744073709551615') > 0)) {
            throw new InvalidArgumentException('The value exceeds the unsigned 64-bit range.');
        }

        // Accumulate in two 32-bit words so the decimal conversion stays in integer arithmetic.
        $low = 0;
        $high = 0;
        for ($i = 0, $length = strlen($value); $i < $length; $i++) {
            $next = $low * 10 + (ord($value[$i]) - ord('0'));
            $high = $high * 10 + ($next >> 32);
            $low = $next & 0xFFFFFFFF;
        }

        $this->writeBytes(pack('V2', $low, $high));
    }

    public function writeHalf(float $value): void
    {
        $this->writeUInt16(BinaryHalf::encode($value));
    }

    public function writeDecimal(string $value): void
    {
        $this->writeBytes(BinaryDecimal::encode($value));
    }

    public function writeSingle(float $value): void
    {
        $this->writeBytes(pack('g', $value));
    }

    public function writeDouble(float $value): void
    {
        $this->writeBytes(pack('e', $value));
    }

    public function write7BitEncodedInt(int $value): void
    {
        $this->write7BitInteger(self::inRange($value, -2147483647 - 1, 2147483647), 0x01FFFFFF);
    }

    public function write7BitEncodedInt64(int $value): void
    {
        self::require64Bit();
        $this->write7BitInteger($value, 0x01FFFFFFFFFFFFFF);
    }

    public function writeBoolean(bool $value): void
    {
        $this->writeBytes($value ? "\x01" : "\x00");
    }

    public function writeString(string $value): void
    {
        $value = EncodedText::encode($value, $this->encoding);
        $length = strlen($value);
        if ($length > 2147483647) {
            throw new InvalidArgumentException('The string is too long for a 32-bit length prefix.');
        }

        $this->write7BitEncodedInt($length);
        $this->writeBytes($value);
    }

    public function writeChar(string $character): void
    {
        EncodedText::validateChar($character);
        $this->writeChars($character);
    }

    public function writeChars(string $characters): void
    {
        $this->writeBytes(EncodedText::encode($characters, $this->encoding));
    }

    public function writeBytes(string $bytes): void
    {
        $this->writer->write($bytes);
    }

    public function close(): void
    {
        $this->writer->close();
    }

    private function write7BitInteger(int $value, int $shiftMask): void
    {
        $bytes = '';
        while ($value < 0 || $value >= 128) {
            $bytes .= chr(($value & 0x7F) | 0x80);
            $value = ($value >> 7) & $shiftMask;
        }

        $this->writeBytes($bytes . chr($value));
    }

    private static function inRange(int $value, int $min, int $max): int
    {
        if ($value < $min || $value > $max) {
            throw new InvalidArgumentException("Expected an integer between $min and $max.");
        }

        return $value;
    }

    private static function require64Bit(): void
    {
        if (PHP_INT_SIZE < 8) {
            throw new RuntimeException('This binary operation requires 64-bit PHP.');
        }
    }
}
