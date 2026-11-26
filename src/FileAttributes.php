<?php

declare(strict_types=1);

namespace Light\IO;

final class FileAttributes
{
    public const int ReadOnly = 0x01;
    public const int Hidden = 0x02;
    public const int System = 0x04;
    public const int Directory = 0x10;
    public const int Archive = 0x20;
    public const int Normal = 0x80;
    public const int ReparsePoint = 0x400;

    private function __construct()
    {
    }
}
