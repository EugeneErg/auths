<?php

declare(strict_types=1);

namespace EugeneErg\Auths\Contracts;

interface ValueObjectInterface
{
    public function isEqual(self $value): bool;
}
