<?php

declare(strict_types=1);

namespace EuegeneErg\Auths\Contracts\Repositories\Read;

use EuegeneErg\Auths\DataTransferObjects\ScenarioStep;
use EuegeneErg\Auths\ValueObjects\AuthIdentityValue;
use EuegeneErg\Auths\ValueObjects\ProviderType;
use EuegeneErg\Auths\ValueObjects\ScenarioStepExternalId;
use EuegeneErg\Auths\ValueObjects\ScenarioStepId;

interface ReadScenarioStepRepositoryInterface
{
    public function findByMessage(
        ProviderType $type,
        AuthIdentityValue $value,
        ScenarioStepExternalId $externalId,
    ): ?ScenarioStep;
    public function findLast(ProviderType $type, AuthIdentityValue $value): ?ScenarioStep;

    public function findById(ScenarioStepId $id): ?ScenarioStep;
}