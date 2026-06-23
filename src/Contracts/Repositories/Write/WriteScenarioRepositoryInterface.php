<?php

declare(strict_types=1);

namespace EugeneErg\Auths\Contracts\Repositories\Write;

use DateTimeInterface;
use EugeneErg\Auths\DataTransferObjects\ScenarioResult;
use EugeneErg\Auths\Entities\Scenario;
use EugeneErg\Auths\ValueObjects\ChannelAddress;
use EugeneErg\Auths\ValueObjects\ProviderType;
use EugeneErg\Auths\ValueObjects\UserId;

interface WriteScenarioRepositoryInterface
{
    public function create(
        string $name,
        ProviderType $type,
        ChannelAddress $address,
        DateTimeInterface $createdAt,
        UserId|null $userId = null,
        ScenarioResult|null $result = null,
    ): Scenario;

    public function update(
        Scenario $scenario,
        ScenarioResult $result,
        UserId|null $userId = null,
    ): Scenario;
}
