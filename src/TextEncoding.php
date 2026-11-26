<?php

declare(strict_types=1);

namespace Light\IO;

enum TextEncoding: string
{
    case Utf8 = 'UTF-8';
    case Utf16LE = 'UTF-16LE';
    case Utf16BE = 'UTF-16BE';
    case Utf32LE = 'UTF-32LE';
    case Utf32BE = 'UTF-32BE';
}
