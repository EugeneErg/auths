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
     * Все привязанные аккаунты пользователя.
     * Используется для отображения списка провайдеров и массового detach.
     *
     * @return AuthIdentity[]
     */
    public function findAllByUserId(UserId $userId): array;
}
