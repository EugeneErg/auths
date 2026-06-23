<?php

declare(strict_types=1);

namespace EugeneErg\Auths\DataTransferObjects;

use EugeneErg\Auths\ValueObjects\AuthVerificationToken;
use EugeneErg\Auths\ValueObjects\OAuthState;

final readonly class OAuthStateOptions
{
    public function __construct(public OAuthState $state, public AuthVerificationToken $token)
    {
    }
}