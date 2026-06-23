<?php

declare(strict_types=1);

namespace EugeneErg\Auths\ValueObjects;

enum CodeType: string
{
    /**
     * Сервис выдаёт токен пользователю, пользователь отправляет его нам обратно.
     */
    case Issued = 'issued';

    /**
     * Сервис отправляет код на контакт пользователя (SMS, email). Пользователь вводит его.
     */
    case Sent = 'sent';

    /**
     * Пользователь проходит OAuth у провайдера, провайдер возвращает code в callback.
     */
    case Callback = 'callback';

    /**
     * Сервис генерирует state для OAuth, отдаёт фронту. Фронт строит URL к провайдеру.
     */
    case OAuthState = 'oauth_state';
}
