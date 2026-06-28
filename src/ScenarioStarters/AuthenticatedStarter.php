<?php

declare(strict_types=1);

namespace EugeneErg\Auths\ScenarioStarters;

use DateInterval;
use EugeneErg\Auths\ValueObjects\Action;
use EugeneErg\Auths\ValueObjects\ChannelAddress;
use EugeneErg\Auths\ValueObjects\CodeType;
use EugeneErg\Auths\ValueObjects\UserId;

/**
 * Стартер для авторизованного пользователя.
 * Action явный — attach, remove, confirm_payment и т.д.
 *
 * Методы возвращают ScenarioContext — передаётся в AuthService::startScenario().
 */
final readonly class AuthenticatedStarter
{
    public function __construct(
        private UserId $userId,
        private Action $action,
    ) {
    }

    /**
     * Мы отправим код на адрес.
     * Адрес опционален — если null, сервис возьмёт из primary identity пользователя.
     */
    public function withSentCode(ChannelAddress|null $address, string $code, DateInterval $ttl): ScenarioContext
    {
        return new ScenarioContext(
            action: $this->action,
            userId: $this->userId,
            address: $address,
            codeType: CodeType::Sent,
            code: $code,
            ttl: $ttl,
        );
    }

    /**
     * Мы выдаём код пользователю (deeplink, QR).
     */
    public function withIssuedCode(string $code, DateInterval $ttl): ScenarioContext
    {
        return new ScenarioContext(
            action: $this->action,
            userId: $this->userId,
            codeType: CodeType::Issued,
            code: $code,
            ttl: $ttl,
        );
    }

    /**
     * OAuth: генерируем state, фронт строит URL к провайдеру.
     */
    public function withOAuth(DateInterval $ttl): ScenarioContext
    {
        return new ScenarioContext(
            action: $this->action,
            userId: $this->userId,
            codeType: CodeType::OAuthState,
            ttl: $ttl,
        );
    }

    /**
     * Просто отправляем сценарий без верификации — уведомления, опросы, онбординг.
     * Адрес опционален — если null, сервис возьмёт из primary identity пользователя.
     */
    public function send(ChannelAddress|null $address = null): ScenarioContext
    {
        return new ScenarioContext(
            action: $this->action,
            userId: $this->userId,
            address: $address,
        );
    }
}