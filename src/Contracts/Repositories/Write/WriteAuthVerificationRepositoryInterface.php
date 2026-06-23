<?php

declare(strict_types=1);

namespace EuegeneErg\Auths\Contracts\Repositories\Write;

use DateTimeImmutable;
use EuegeneErg\Auths\Entities\AuthVerification;
use EuegeneErg\Auths\ValueObjects\Action;
use EuegeneErg\Auths\ValueObjects\InscribedCode;
use EuegeneErg\Auths\ValueObjects\IssuedCode;
use EuegeneErg\Auths\ValueObjects\ProviderType;
use EuegeneErg\Auths\ValueObjects\SentCode;
use EuegeneErg\Auths\ValueObjects\UserId;

interface WriteAuthVerificationRepositoryInterface
{
    public function create(
        ProviderType $type,
        InscribedCode|IssuedCode|SentCode $code,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $expiresAt,
        Action $action,
        ?UserId $userId = null,
    ): AuthVerification;
}