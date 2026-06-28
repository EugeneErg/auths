<?php

declare(strict_types=1);

namespace EugeneErg\Auths\ScenarioStarters;

use EugeneErg\Auths\ValueObjects\Action;
use EugeneErg\Auths\ValueObjects\ChannelAddress;
use EugeneErg\Auths\ValueObjects\UserId;

/**
 * Стартер для ScenarioService — только отправка без верификации.
 */
final readonly class ScenarioStarter
{
    public function withUser(UserId $userId, Action $action): AuthenticatedStarter
    {
        return new AuthenticatedStarter($userId, $action);
    }

    public function send(ChannelAddress $address, Action $action): ScenarioContext
    {
        return new ScenarioContext(action: $action, address: $address);
    }
}
