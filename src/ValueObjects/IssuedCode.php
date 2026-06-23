<?php

declare(strict_types = 1);

namespace EuegeneErg\Auths\ValueObjects;

final readonly class IssuedCode
{
    public function __construct(public string $value)
    {
    }
}