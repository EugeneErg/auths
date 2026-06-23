<?php

declare(strict_types=1);

namespace EugeneErg\Auths\Exceptions;

use Exception;

class AuthVerificationInvalidException extends Exception implements AuthExceptionInterface
{
    public function __construct(string $message = 'Auth verification invalid.')
    {
        parent::__construct($message);
    }
}
