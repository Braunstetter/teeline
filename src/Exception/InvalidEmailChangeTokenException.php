<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

final class InvalidEmailChangeTokenException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The email change token is invalid or has expired.');
    }
}
