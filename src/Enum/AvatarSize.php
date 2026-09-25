<?php

declare(strict_types=1);

namespace App\Enum;

enum AvatarSize: int
{
    case Small = 128;
    case Large = 1024;
}
