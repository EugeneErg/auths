<?php

declare(strict_types=1);

namespace EugeneErg\Auths\ScenarioStarters;

use DateInterval;
use EugeneErg\Auths\ValueObjects\Action;
use EugeneErg\Auths\ValueObjects\ChannelAddress;
use EugeneErg\Auths\ValueObjects\CodeType;
use EugeneErg\Auths\ValueObjects\UserId;

/**
 * Стартер для авторизованного пользователя в AuthService.
 * Action задаётся при создании (через withUser), зашит в вызываемом методе AuthService.
 */
final readonly class AuthenticatedStarter
{
    public function __construct(
        private UserId $userId,
    ) {
    }

    public function withSentCode(ChannelAddress|null $address, string $code, DateInterval $ttl): AuthContext
    {
        return new AuthContext(userId: $this->userId, address: $address, codeType: CodeType::Sent, code: $code, ttl: $ttl);
    }

    public function withIssuedCode(string $code, DateInterval $ttl): AuthContext
    {
        return new AuthContext(userId: $this->userId, codeType: CodeType::Issued, code: $code, ttl: $ttl);
    }

    public function withOAuth(DateInterval $ttl): AuthContext
    {
        return new AuthContext(userId: $this->userId, codeType: CodeType::OAuthState, ttl: $ttl);
    }

    public function send(ChannelAddress|null $address = null): AuthContext
    {
        return new AuthContext(userId: $this->userId, address: $address);
    }
}
