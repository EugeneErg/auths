<?php

declare(strict_types=1);

namespace EugeneErg\Auths\Entities;

use DateTimeImmutable;
use EugeneErg\Auths\DataTransferObjects\ScenarioResult;
use EugeneErg\Auths\ValueObjects\Action;
use EugeneErg\Auths\ValueObjects\ChannelAddress;
use EugeneErg\Auths\ValueObjects\ProviderType;
use EugeneErg\Auths\ValueObjects\ScenarioId;
use EugeneErg\Auths\ValueObjects\UserId;

final readonly class Scenario
{
    public function __construct(
        public ScenarioId $id,
        public string $name,
        public ChannelAddress $address,
        public ProviderType $type,
        public Action $action,
        public DateTimeImmutable $createdAt,
        public UserId|null $userId = null,
        public ScenarioResult|null $result = null,
    ) {
    }
}
