<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

final class EmailChangeThrottledException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Too many email change requests.');
    }
}
