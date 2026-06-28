<?php

declare(strict_types=1);

namespace EugeneErg\Auths\Contracts\Repositories\Read;

use EugeneErg\Auths\Entities\AuthVerification;
use EugeneErg\Auths\ValueObjects\AuthVerificationToken;
use EugeneErg\Auths\ValueObjects\ChannelAddress;
use EugeneErg\Auths\ValueObjects\OAuthState;
use EugeneErg\Auths\ValueObjects\ProviderType;

interface ReadAuthVerificationRepositoryInterface
{
    public function findByToken(AuthVerificationToken $token): AuthVerification|null;

    public function findByOAuthState(ProviderType $type, OAuthState $state): AuthVerification|null;

    /**
     * Найти активную (не использованную, не истёкшую) верификацию для данного адреса и сценария.
     * Используется в handleWebhook для сверки verificationCode из Response.
     */
    public function findActiveByAddress(ProviderType $type, ChannelAddress $address): AuthVerification|null;
}
