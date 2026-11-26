<?php

declare(strict_types=1);

namespace Light\IO;

final class UnixFileMode
{
    public const int None = 0;
    public const int OtherExecute = 00001;
    public const int OtherWrite = 00002;
    public const int OtherRead = 00004;
    public const int GroupExecute = 00010;
    public const int GroupWrite = 00020;
    public const int GroupRead = 00040;
    public const int UserExecute = 00100;
    public const int UserWrite = 00200;
    public const int UserRead = 00400;
    public const int StickyBit = 01000;
    public const int SetGroup = 02000;
    public const int SetUser = 04000;

    private function __construct()
    {
    }
}
