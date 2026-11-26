<?php

declare(strict_types=1);

namespace Light\IO;

final class FileShare
{
    public const int None = 0;
    public const int Read = 1;
    public const int Write = 2;
    public const int ReadWrite = self::Read | self::Write;
    public const int Delete = 4;

    private function __construct()
    {
    }
}
