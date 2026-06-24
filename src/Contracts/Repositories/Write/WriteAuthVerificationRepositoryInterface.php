<?php

declare(strict_types=1);

namespace EugeneErg\Auths\Contracts\Repositories\Write;

use DateTimeImmutable;
use EugeneErg\Auths\Entities\AuthVerification;
use EugeneErg\Auths\ValueObjects\Action;
use EugeneErg\Auths\ValueObjects\IssuedCode;
use EugeneErg\Auths\ValueObjects\OAuthState;
use EugeneErg\Auths\ValueObjects\ProviderType;
use EugeneErg\Auths\ValueObjects\SentCode;
use EugeneErg\Auths\ValueObjects\UserId;

interface WriteAuthVerificationRepositoryInterface
{
    public function create(
        ProviderType $type,
        IssuedCode|SentCode|OAuthState $code,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $expiresAt,
        Action $action,
        UserId|null $userId = null,
    ): AuthVerification;

    /**
     * Помечает верификацию использованной. Повторный verify() бросит AlreadyUsed.
     */
    public function consume(AuthVerification $verification, DateTimeImmutable $consumedAt): void;
}
