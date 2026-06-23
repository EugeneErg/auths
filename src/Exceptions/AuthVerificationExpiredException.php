<?php

declare(strict_types=1);

namespace EugeneErg\Auths\Exceptions;

use Exception;

class AuthVerificationExpiredException extends Exception implements AuthExceptionInterface
{
    public function __construct(string $message = 'Auth verification expired.')
    {
        parent::__construct($message);
    }
}
