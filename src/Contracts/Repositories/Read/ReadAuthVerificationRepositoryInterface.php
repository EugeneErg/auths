<?php

declare(strict_types=1);

namespace EugeneErg\Auths\Contracts\Repositories\Read;

use EugeneErg\Auths\Entities\AuthVerification;
use EugeneErg\Auths\ValueObjects\OAuthState;
use EugeneErg\Auths\ValueObjects\ProviderType;

interface ReadAuthVerificationRepositoryInterface
{
    /**
     * Найти верификацию по токену (используется когда пользователь возвращает token нам).
     */
    public function findByToken(string $token): AuthVerification|null;

    /**
     * Найти верификацию по OAuth state (используется в callback от OAuth провайдера).
     */
    public function findByOAuthState(ProviderType $type, OAuthState $state): AuthVerification|null;
}
