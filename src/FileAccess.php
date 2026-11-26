<?php

declare(strict_types=1);

namespace Light\IO;

enum FileAccess
{
    case Read;
    case Write;
    case ReadWrite;
}
