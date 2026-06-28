<?php

declare(strict_types=1);

namespace EugeneErg\Auths\ScenarioStarters;

use DateInterval;
use EugeneErg\Auths\ValueObjects\Action;
use EugeneErg\Auths\ValueObjects\ChannelAddress;
use EugeneErg\Auths\ValueObjects\CodeType;
use EugeneErg\Auths\ValueObjects\UserId;

/**
 * Стартер для анонимного пользователя в AuthService.
 * Action зашит в вызываемом методе AuthService (authorize, etc.).
 */
final readonly class AnonymousStarter
{
    public static function withUser(UserId $userId, Action $action): AuthenticatedStarter
    {
        return new AuthenticatedStarter($userId, $action);
    }

    public static function withSentCode(ChannelAddress $address, string $code, DateInterval $ttl): AuthContext
    {
        return new AuthContext(address: $address, codeType: CodeType::Sent, code: $code, ttl: $ttl);
    }

    public static function withIssuedCode(string $code, DateInterval $ttl): AuthContext
    {
        return new AuthContext(codeType: CodeType::Issued, code: $code, ttl: $ttl);
    }

    public static function withOAuth(DateInterval $ttl): AuthContext
    {
        return new AuthContext(codeType: CodeType::OAuthState, ttl: $ttl);
    }
}
