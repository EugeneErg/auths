<?php

declare(strict_types = 1);

namespace EuegeneErg\Auths\ValueObjects;

final readonly class MessageId
{
    public function __construct(public string $value)
    {
    }
}