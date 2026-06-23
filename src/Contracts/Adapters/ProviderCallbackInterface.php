<?php

declare(strict_types=1);

namespace EugeneErg\Auths\Contracts\Adapters;

use EugeneErg\Auths\ValueObjects\CallbackCode;
use EugeneErg\Auths\ValueObjects\ChannelAddress;
use EugeneErg\Auths\ValueObjects\OAuthState;

/**
 * Провайдер OAuth/SSO.
 *
 * Флоу:
 *   1. Сервис генерирует state, сохраняет как AuthVerification, отдаёт фронту.
 *   2. Фронт строит URL провайдера (client_id + redirect_uri + state) — сервис в этом не участвует.
 *   3. Пользователь авторизуется у провайдера, тот редиректит на callback с code+state.
 *   4. Сервис вызывает exchangeCode() — провайдер меняет code на токен и возвращает ChannelAddress.
 */
interface ProviderCallbackInterface extends ProviderInterface
{
    /**
     * Обменивает OAuth code на идентификатор пользователя у провайдера.
     * Внутри делает запрос к провайдеру (token endpoint + userinfo).
     */
    public function exchangeCode(CallbackCode $code, OAuthState $state): ChannelAddress;
}
