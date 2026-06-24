<?php

declare(strict_types=1);

namespace EugeneErg\Auths\DataTransferObjects;

use EugeneErg\Auths\Entities\AuthIdentity;
use EugeneErg\Auths\ValueObjects\ChannelAddress;
use EugeneErg\Auths\ValueObjects\UserId;

/**
 * Результат завершённого флоу (verify + действие в одном вызове).
 * Возвращается из completeRegistration / completeAttach / completeDetach.
 */
final readonly class CompleteResult
{
    public function __construct(
        public UserId $userId,
        public ChannelAddress $address,
        public AuthIdentity $identity,
    ) {
    }
}
