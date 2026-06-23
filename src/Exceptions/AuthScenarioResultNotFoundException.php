<?php

declare(strict_types=1);

namespace EugeneErg\Auths\Exceptions;

use Exception;

class AuthScenarioResultNotFoundException extends Exception implements AuthExceptionInterface
{
    public function __construct(string $message = 'Scenario result not found.')
    {
        parent::__construct($message);
    }
}
