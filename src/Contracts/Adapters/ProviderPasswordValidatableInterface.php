<?php

declare(strict_types=1);

namespace EugeneErg\Auths\Contracts\Adapters;

/**
 * Провайдер с классической парольной аутентификацией (login/password).
 */
interface ProviderPasswordValidatableInterface extends ProviderInterface
{
    public function getExternalIdByPassword(string $login, string $password): string|null;
}
