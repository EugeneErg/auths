<?php

declare(strict_types=1);

namespace EugeneErg\Auths\Exceptions;

use Exception;

class AuthVerificationAlreadyUsedException extends Exception implements AuthExceptionInterface
{
    public function __construct(string $message = 'Auth verification already used.')
    {
        parent::__construct($message);
    }
}
