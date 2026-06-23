<?php

declare(strict_types=1);

namespace EugeneErg\Auths\Entities;

use DateTimeImmutable;

final readonly class AuthIdentity
{
    public function __construct(
        public string $type,
        public string $address,
        public string $userId,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable|null $disconnectedAt = null,
        public DateTimeImmutable|null $deleteAt = null,
    ) {
    }
}
