<?php

declare(strict_types=1);

namespace EuegeneErg\Auths\Entities;

use DateTimeImmutable;
use EuegeneErg\Auths\ValueObjects\CodeType;

final readonly class AuthVerification
{
    public function __construct(
        public string $token,
        public string $type,
        public ?string $value,
        public string $code,
        public CodeType $codeType,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $expiresAt,
        public string $action,
        public ?string $userId = null,
    ) {
    }
}
