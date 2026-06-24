<?php

declare(strict_types=1);

namespace EugeneErg\Auths\Contracts\Repositories\Read;

use EugeneErg\Auths\Entities\AuthVerification;
use EugeneErg\Auths\ValueObjects\AuthVerificationToken;
use EugeneErg\Auths\ValueObjects\OAuthState;
use EugeneErg\Auths\ValueObjects\ProviderType;

interface ReadAuthVerificationRepositoryInterface
{
    /**
     * Найти верификацию по токену (IssuedCode / SentCode флоу).
     */
    public function findByToken(AuthVerificationToken $token): AuthVerification|null;

    /**
     * Найти верификацию по OAuth state.
     */
    public function findByOAuthState(ProviderType $type, OAuthState $state): AuthVerification|null;
}
