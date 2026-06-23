<?php

declare(strict_types=1);

namespace EugeneErg\Auths\Contracts\Repositories\Write;

use DateTimeImmutable;
use EugeneErg\Auths\Entities\AuthIdentity;
use EugeneErg\Auths\ValueObjects\ChannelAddress;
use EugeneErg\Auths\ValueObjects\ProviderType;
use EugeneErg\Auths\ValueObjects\UserId;

interface WriteAuthIdentityRepositoryInterface
{
    /**
     * Создаёт новую связь пользователя с внешним аккаунтом (регистрация или attach).
     */
    public function create(
        ProviderType $type,
        ChannelAddress $address,
        UserId $userId,
        DateTimeImmutable $createdAt,
    ): AuthIdentity;

    /**
     * Отвязывает внешний аккаунт от пользователя (detach / remove).
     */
    public function detach(
        AuthIdentity $identity,
        DateTimeImmutable $disconnectedAt,
    ): void;

    /**
     * Планирует удаление аккаунта (отложенное удаление с оповещением).
     */
    public function scheduleDelete(
        AuthIdentity $identity,
        DateTimeImmutable $deleteAt,
    ): void;
}
