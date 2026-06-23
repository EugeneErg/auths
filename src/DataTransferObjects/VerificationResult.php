<?php

declare(strict_types=1);

namespace EugeneErg\Auths\DataTransferObjects;

use EugeneErg\Auths\Entities\AuthIdentity;
use EugeneErg\Auths\ValueObjects\Action;
use EugeneErg\Auths\ValueObjects\ChannelAddress;
use EugeneErg\Auths\ValueObjects\UserId;

/**
 * Результат успешной верификации.
 * На основе action приложение решает что делать дальше (создать юзера, залогинить и т.д.).
 */
final readonly class VerificationResult
{
    public function __construct(
        public Action $action,
        public ChannelAddress $address,
        /**
         * Уже существующий userId, если аккаунт с таким address уже привязан.
         */
        public UserId|null $userId,
        /**
         * Уже существующий identity, если найден.
         */
        public AuthIdentity|null $identity,
    ) {
    }
}
