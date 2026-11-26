<?php

declare(strict_types=1);

namespace Light\IO;

/** @internal */
final class BinaryHalf
{
    public static function encode(float $value): int
    {
        $high = unpack('n', pack('E', $value))[1];
        $sign = $high & 0x8000;
        $magnitude = abs($value);
        if (is_nan($value)) {
            return $sign | 0x7E00;
        }

        if ($magnitude >= 65520.0) {
            return $sign | 0x7C00;
        }

        $exponent = (($high >> 4) & 0x7FF) - 1023;
        // Subnormals are integer multiples of 2^-24.
        $scaled = $magnitude * ($exponent < -14 ? 16777216.0 : 2.0 ** (10 - $exponent));

        $rounded = (int) floor($scaled);
        $fraction = $scaled - $rounded;
        if ($fraction > 0.5 || ($fraction === 0.5 && ($rounded & 1) !== 0)) {
            $rounded++;
        }

        // In the normal range the rounded significand includes its implicit leading bit.
        return $sign | ($exponent < -14 ? $rounded : (($exponent + 14) << 10) + $rounded);
    }

    public static function decode(int $bits): float
    {
        $exponent = ($bits >> 10) & 31;
        $fraction = $bits & 1023;
        $value = match ($exponent) {
            0 => $fraction * (2.0 ** -24),
            31 => $fraction === 0 ? INF : NAN,
            default => (1024 + $fraction) * (2.0 ** ($exponent - 25)),
        };

        return ($bits & 0x8000) !== 0 ? -$value : $value;
    }
}
