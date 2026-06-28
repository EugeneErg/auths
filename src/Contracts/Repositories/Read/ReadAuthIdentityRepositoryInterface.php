<?php

declare(strict_types=1);

namespace EugeneErg\Auths\Contracts\Repositories\Read;

use EugeneErg\Auths\Entities\AuthIdentity;
use EugeneErg\Auths\ValueObjects\ChannelAddress;
use EugeneErg\Auths\ValueObjects\ProviderType;
use EugeneErg\Auths\ValueObjects\UserId;

interface ReadAuthIdentityRepositoryInterface
{
    public function exists(UserId $userId, ProviderType $type): bool;

    public function find(ProviderType $type, ChannelAddress $address): AuthIdentity|null;

    /**
     * Основной аккаунт пользователя для провайдера (isPrimary=true),
     * или последний привязанный если основной не задан.
     */
    public function findPrimary(UserId $userId, ProviderType $type): AuthIdentity|null;

    /**
     * Все привязанные аккаунты пользователя.
     *
     * @return AuthIdentity[]
     */
    public function findAllByUserId(UserId $userId): array;
}
