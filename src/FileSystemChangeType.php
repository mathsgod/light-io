<?php

declare(strict_types=1);

namespace Light\IO;

enum FileSystemChangeType
{
    case Created;
    case Changed;
    case Deleted;
    case Renamed;
}
