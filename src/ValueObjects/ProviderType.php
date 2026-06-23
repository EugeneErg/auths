<?php

declare(strict_types=1);

namespace EugeneErg\Auths\ValueObjects;

final readonly class ProviderType
{
    public function __construct(public string $value)
    {
    }
}
