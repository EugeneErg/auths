<?php

declare(strict_types=1);

namespace EugeneErg\Auths\Contracts\Adapters;

use EugeneErg\Auths\ValueObjects\ChannelAddress;
use EugeneErg\Auths\ValueObjects\OAuthState;

/**
 * Провайдер OAuth/SSO.
 *
 * Флоу:
 *   1. Сервис генерирует state, сохраняет как AuthVerification, отдаёт фронту через OAuthStateOptions.
 *   2. Фронт строит URL провайдера (client_id + redirect_uri + state) — сервис не участвует.
 *   3. Пользователь авторизуется, провайдер редиректит на callback с code+state.
 *   4. Сервис вызывает exchangeCode() — провайдер меняет code на токен и возвращает ChannelAddress.
 */
interface ProviderCallbackInterface extends ProviderInterface
{
    /**
     * Обменивает OAuth code на идентификатор пользователя у провайдера.
     * Внутри делает запрос к провайдеру (token endpoint + userinfo).
     *
     * @param string      $code     Код от провайдера из callback
     * @param OAuthState  $state    State, сгенерированный нами ранее
     * @param string|null $redirect redirect_uri, если требуется при обмене
     */
    public function exchangeCode(string $code, OAuthState $state, string|null $redirect): ChannelAddress;
}
