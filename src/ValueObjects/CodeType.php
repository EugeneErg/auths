<?php

declare(strict_types=1);

namespace EugeneErg\Auths\ValueObjects;

enum CodeType: string
{
    /** Сервис выдаёт код пользователю, пользователь отправляет его нам обратно. */
    case Issued = 'issued';

    /** Сервис отправляет код на контакт пользователя (SMS, email). Пользователь вводит его. */
    case Sent = 'sent';

    /** Сервис генерирует state для OAuth, отдаёт фронту. Фронт строит URL к провайдеру. */
    case OAuthState = 'oauth_state';
}
