<?php

declare(strict_types=1);

namespace EugeneErg\Auths\ScenarioStarters;

use DateInterval;
use EugeneErg\Auths\ValueObjects\ChannelAddress;
use EugeneErg\Auths\ValueObjects\CodeType;
use EugeneErg\Auths\ValueObjects\UserId;

/**
 * Контекст запуска флоу авторизации/привязки/отвязки/подтверждения.
 * Формируется через AnonymousStarter / AuthenticatedStarter.
 * Action зашит в методах AuthService.
 */
final readonly class AuthContext
{
    public function __construct(
        public UserId|null $userId = null,
        public ChannelAddress|null $address = null,
        public CodeType|null $codeType = null,
        public string|null $code = null,
        public DateInterval|null $ttl = null,
    ) {
    }
}
