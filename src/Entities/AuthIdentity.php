<?php

declare(strict_types=1);

namespace EugeneErg\Auths\Entities;

use DateTimeImmutable;
use EugeneErg\Auths\ValueObjects\ChannelAddress;
use EugeneErg\Auths\ValueObjects\ProviderType;
use EugeneErg\Auths\ValueObjects\UserId;

final readonly class AuthIdentity
{
    public function __construct(
        public ProviderType $type,
        public ChannelAddress $address,
        public UserId $userId,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable|null $disconnectedAt = null,
        public DateTimeImmutable|null $deleteAt = null,
    ) {
    }
}
