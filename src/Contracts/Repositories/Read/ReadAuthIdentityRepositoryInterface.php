<?php

declare(strict_types=1);

namespace EuegeneErg\Auths\Contracts\Repositories\Read;

use EuegeneErg\Auths\Entities\AuthIdentity;
use EuegeneErg\Auths\ValueObjects\ProviderType;
use EuegeneErg\Auths\ValueObjects\AuthIdentityValue;
use EuegeneErg\Auths\ValueObjects\UserId;

interface ReadAuthIdentityRepositoryInterface
{

    public function exists(UserId $userId, ProviderType $type): bool;

    public function find(ProviderType $type, AuthIdentityValue $value): ?AuthIdentity;
}