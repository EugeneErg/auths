<?php

declare(strict_types = 1);

namespace EuegeneErg\Auths\ValueObjects;

final readonly class SentCode
{
    public function __construct(public string $value, public AuthIdentityValue $authValue)
    {
    }
}