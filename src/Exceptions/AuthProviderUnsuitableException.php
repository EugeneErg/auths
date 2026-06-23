<?php

declare(strict_types=1);

namespace EuegeneErg\Auths\Exceptions;

use Exception;

class AuthProviderUnsuitableException extends Exception implements AuthExceptionInterface
{
    public function __construct(string $message = 'Auth provider unsuitable.')
    {
        parent::__construct($message);
    }
}