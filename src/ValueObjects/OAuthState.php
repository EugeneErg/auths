<?php

declare(strict_types=1);

namespace EugeneErg\Auths\ValueObjects;

/**
 * State для OAuth-флоу. Генерируется сервисом, передаётся фронту.
 * Фронт вставляет его в URL провайдера. Провайдер возвращает его в callback вместе с code.
 * Пользователь никогда не видит и не вводит state вручную.
 */
final readonly class OAuthState
{
    public function __construct(public string $value)
    {
    }
}
