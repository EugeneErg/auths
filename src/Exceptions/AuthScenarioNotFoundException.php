<?php

declare(strict_types=1);

namespace EugeneErg\Auths\Exceptions;

use Exception;

class AuthScenarioNotFoundException extends Exception implements AuthExceptionInterface
{
    public function __construct(string $message = 'Scenario not found.')
    {
        parent::__construct($message);
    }
}
