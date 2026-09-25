<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

final class EmailAlreadyInUseException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The email address is already in use.');
    }
}
