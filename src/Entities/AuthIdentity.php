<?php

declare(strict_types=1);

namespace EuegeneErg\Auths\Entities;

use DateTimeImmutable;

final readonly class AuthIdentity
{
    public function __construct(
        public string $type,
        public string $value,
        public string $userId,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $disconnectedAt = null,
        public ?DateTimeImmutable $deleteAt = null,
    ) {
    }
}