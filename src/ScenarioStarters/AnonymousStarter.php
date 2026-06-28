<?php

declare(strict_types=1);

namespace EugeneErg\Auths\ScenarioStarters;

use DateInterval;
use EugeneErg\Auths\ValueObjects\Action;
use EugeneErg\Auths\ValueObjects\ChannelAddress;
use EugeneErg\Auths\ValueObjects\CodeType;
use EugeneErg\Auths\ValueObjects\UserId;

/**
 * Стартер для анонимного пользователя.
 * Action = auth зашит — аноним всегда авторизуется.
 *
 * Методы возвращают ScenarioContext — передаётся в AuthService::startScenario().
 */
final readonly class AnonymousStarter
{
    public static function withUser(UserId $userId, Action $action): AuthenticatedStarter
    {
        return new AuthenticatedStarter($userId, $action);
    }

    /**
     * Мы отправим код на указанный адрес, пользователь введёт его на сайте или в форме.
     */
    public static function withSentCode(ChannelAddress $address, string $code, DateInterval $ttl): ScenarioContext
    {
        return new ScenarioContext(
            address: $address,
            codeType: CodeType::Sent,
            code: $code,
            ttl: $ttl,
        );
    }

    /**
     * Мы выдаём код пользователю (deeplink, QR).
     * Адрес появится позже — когда пользователь сам напишет нам.
     */
    public static function withIssuedCode(string $code, DateInterval $ttl): ScenarioContext
    {
        return new ScenarioContext(
            codeType: CodeType::Issued,
            code: $code,
            ttl: $ttl,
        );
    }

    /**
     * OAuth: генерируем state, фронт строит URL к провайдеру.
     */
    public static function withOAuth(DateInterval $ttl): ScenarioContext
    {
        return new ScenarioContext(
            codeType: CodeType::OAuthState,
            ttl: $ttl,
        );
    }
}
