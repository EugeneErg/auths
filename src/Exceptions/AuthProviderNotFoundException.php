<?php

declare(strict_types=1);

namespace EuegeneErg\Auths\Exceptions;

use Exception;

class AuthProviderNotFoundException extends Exception implements AuthExceptionInterface
{
    public function __construct(string $message = 'Auth provider not found.')
    {
        parent::__construct($message);
    }
}