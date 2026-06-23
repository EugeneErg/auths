<?php

declare(strict_types=1);

namespace EugeneErg\Auths\Exceptions;

use Exception;

class AuthTypeAlreadyExistsException extends Exception implements AuthExceptionInterface
{
    public function __construct(string $message = 'Auth type already exists.')
    {
        parent::__construct($message);
    }
}
