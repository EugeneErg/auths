<?php

declare(strict_types=1);

namespace EuegeneErg\Auths\Contracts;

interface TransactionInterface
{
    public function transaction(callable $callback): mixed;
}