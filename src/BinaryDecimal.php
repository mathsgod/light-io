<?php

declare(strict_types=1);

namespace Light\IO;

use InvalidArgumentException;
use RuntimeException;

/** @internal */
final class BinaryDecimal
{
    public static function encode(string $value): string
    {
        if (preg_match('/\A(-?)([0-9]+)(?:\.([0-9]+))?\z/', $value, $parts) !== 1) {
            throw new InvalidArgumentException('Expected a decimal string.');
        }

        $fraction = $parts[3] ?? '';
        $scale = strlen($fraction);
        $digits = ltrim($parts[2] . $fraction, '0');
        if ($scale > 28 || strlen($digits) > 29) {
            throw new InvalidArgumentException('The value exceeds the .NET Decimal range or scale.');
        }

        $bytes = str_repeat("\0", 12);
        for ($i = 0, $length = strlen($digits); $i < $length; $i++) {
            $carry = (int) $digits[$i];
            for ($j = 0; $j < 12; $j++) {
                $carry += ord($bytes[$j]) * 10;
                $bytes[$j] = chr($carry & 255);
                $carry >>= 8;
            }

            if ($carry !== 0) {
                throw new InvalidArgumentException('The value exceeds the .NET Decimal range.');
            }
        }

        return $bytes . "\0\0" . chr($scale) . ($parts[1] === '-' ? "\x80" : "\0");
    }

    public static function decode(string $bytes): string
    {
        $scale = ord($bytes[14]);
        $sign = ord($bytes[15]);
        if ($bytes[12] !== "\0" || $bytes[13] !== "\0" || $scale > 28 || ($sign !== 0 && $sign !== 128)) {
            throw new RuntimeException('Invalid .NET Decimal flags.');
        }

        $digits = '0';
        for ($i = 11; $i >= 0; $i--) {
            $carry = ord($bytes[$i]);
            for ($j = strlen($digits) - 1; $j >= 0; $j--) {
                $carry += (int) $digits[$j] * 256;
                $digits[$j] = (string) ($carry % 10);
                $carry = intdiv($carry, 10);
            }

            if ($carry !== 0) {
                $digits = $carry . $digits;
            }
        }

        if ($scale !== 0) {
            $digits = str_pad($digits, $scale + 1, '0', STR_PAD_LEFT);
            $digits = substr($digits, 0, -$scale) . '.' . substr($digits, -$scale);
        }

        return ($sign === 128 ? '-' : '') . $digits;
    }
}
