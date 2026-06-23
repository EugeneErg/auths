<?php

declare(strict_types=1);

namespace EuegeneErg\Auths\Contracts\Repositories\Write;

use DateTimeInterface;
use EuegeneErg\Auths\DataTransferObjects\ScenarioResult;
use EuegeneErg\Auths\Entities\Scenario;
use EuegeneErg\Auths\ValueObjects\AuthIdentityValue;
use EuegeneErg\Auths\ValueObjects\ProviderType;
use EuegeneErg\Auths\ValueObjects\UserId;

interface WriteScenarioRepositoryInterface
{
    public function create(
        string $name,
        ProviderType $type,
        AuthIdentityValue $value,
        DateTimeInterface $createdAt,
        ?UserId $userId = null,
        ?ScenarioResult $result = null,
    ): Scenario;

    public function update(
        Scenario $scenario,
        ScenarioResult $result,
        ?UserId $userId = null,
    ): Scenario;
}