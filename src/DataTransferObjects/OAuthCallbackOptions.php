<?php

declare(strict_types=1);

namespace EugeneErg\Auths\DataTransferObjects;

use EugeneErg\Auths\ValueObjects\CallbackCode;
use EugeneErg\Auths\ValueObjects\OAuthState;
use EugeneErg\Auths\ValueObjects\ProviderType;

/**
 * Данные, пришедшие в OAuth callback от провайдера.
 * code — выдан провайдером, state — тот, что сервис ранее сгенерировал и отдал фронту.
 */
final readonly class OAuthCallbackOptions
{
    public function __construct(
        public ProviderType $type,
        public CallbackCode $code,
        public OAuthState $state,
    ) {
    }
}
