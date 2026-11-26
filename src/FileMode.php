<?php

declare(strict_types=1);

namespace Light\IO;

enum FileMode
{
    case CreateNew;
    case Create;
    case Open;
    case OpenOrCreate;
    case Truncate;
    case Append;
}
