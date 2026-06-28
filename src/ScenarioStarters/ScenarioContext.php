<?php


declare(strict_types=1);

namespace EugeneErg\Auths\ScenarioStarters;

use DateInterval;
use EugeneErg\Auths\ValueObjects\Action;
use EugeneErg\Auths\ValueObjects\ChannelAddress;
use EugeneErg\Auths\ValueObjects\CodeType;
use EugeneErg\Auths\ValueObjects\UserId;

/**
 * Контекст запуска сценария — результат цепочки вызовов стартера.
 * Передаётся в AuthService::startScenario() и содержит всё необходимое для запуска.
 */
final readonly class ScenarioContext
{
    public function __construct(
        public Action|null $action = null,
        public UserId|null $userId = null,
        public ChannelAddress|null $address = null,
        public CodeType|null $codeType = null,
        public string|null $code = null,
        public DateInterval|null $ttl = null,
    ) {
    }
}
