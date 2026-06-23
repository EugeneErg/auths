<?php

declare(strict_types=1);

namespace EuegeneErg\Auths\Contracts\Adapters;

interface ProviderPasswordValidatableInterface extends ProviderInterface
{
    public function getExternalIdByPassword(string $login, string $password): ?string;
}