<?php

declare(strict_types=1);

namespace EugeneErg\Auths\Entities;

use DateTimeImmutable;
use EugeneErg\Auths\ValueObjects\Action;
use EugeneErg\Auths\ValueObjects\AuthVerificationToken;
use EugeneErg\Auths\ValueObjects\ChannelAddress;
use EugeneErg\Auths\ValueObjects\CodeType;
use EugeneErg\Auths\ValueObjects\ProviderType;
use EugeneErg\Auths\ValueObjects\UserId;

final readonly class AuthVerification
{
    public function __construct(
        public AuthVerificationToken $token,
        public ProviderType $type,
        public ChannelAddress|null $address,
        public string $code,
        public CodeType $codeType,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $expiresAt,
        public Action $action,
        public UserId|null $userId = null,
        /**
         * Заполняется когда верификация использована. Повторный verify() будет отклонён.
         */
        public DateTimeImmutable|null $consumedAt = null,
    ) {
    }

    public function isExpired(DateTimeImmutable $now): bool
    {
        return $now > $this->expiresAt;
    }

    public function isConsumed(): bool
    {
        return $this->consumedAt !== null;
    }
}
