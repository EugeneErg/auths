<?php

declare(strict_types=1);

namespace EuegeneErg\Auths\Contracts\Adapters;

use EuegeneErg\Auths\ValueObjects\Action;
use EuegeneErg\Auths\ValueObjects\AuthIdentityValue;

interface ProviderSendMessageInterface extends ProviderInterface
{
    /**
     * @return string next step
     */
    public function sendMessage(AuthIdentityValue $value, array $properties, Action $action, ?string $step): string;
}