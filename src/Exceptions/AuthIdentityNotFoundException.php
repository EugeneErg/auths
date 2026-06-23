<?php

declare(strict_types=1);

namespace EugeneErg\Auths\Exceptions;

use Exception;

class AuthIdentityNotFoundException extends Exception implements AuthExceptionInterface
{
    public function __construct(string $message = 'Auth identity not found.')
    {
        parent::__construct($message);
    }
}
