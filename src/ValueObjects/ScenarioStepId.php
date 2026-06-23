<?php

declare(strict_types = 1);

namespace EuegeneErg\Auths\ValueObjects;

use EuegeneErg\Auths\Contracts\ValueObjectInterface;

final readonly class ScenarioStepId implements ValueObjectInterface
{
    public function __construct(public string $value)
    {
    }

    public function isEqual(ValueObjectInterface $value): bool
    {
        return $value instanceof self && $value->value === $this->value;
    }
}