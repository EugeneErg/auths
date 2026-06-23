<?php

declare(strict_types=1);

namespace EuegeneErg\Auths\Contracts;

interface ValueObjectInterface
{
    public function isEqual(self $value): bool;
}