<?php

declare(strict_types=1);

namespace EugeneErg\Auths\Contracts;

interface TransactionInterface
{
    public function transaction(callable $callback): mixed;
}
