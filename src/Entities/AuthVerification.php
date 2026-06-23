<?php

declare(strict_types=1);

namespace EugeneErg\Auths\Entities;

use DateTimeImmutable;
use EugeneErg\Auths\ValueObjects\CodeType;

final readonly class AuthVerification
{
    public function __construct(
        public string $token,
        public string $type,
        /**
         * Адрес канала (email, телефон, tg_id). Null для OAuth (адрес неизвестен до callback).
         */
        public string|null $address,
        public string $code,
        public CodeType $codeType,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $expiresAt,
        public string $action,
        public string|null $userId = null,
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
