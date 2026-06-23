<?php

declare(strict_types=1);

namespace EuegeneErg\Auths\Contracts\Repositories\Write;

use DateTimeImmutable;
use EuegeneErg\Auths\Entities\ScenarioStep;
use EuegeneErg\Auths\ValueObjects\ScenarioId;
use EuegeneErg\Auths\ValueObjects\ScenarioStepExternalId;
use EuegeneErg\Auths\ValueObjects\ScenarioStepId;

interface WriteScenarioStepRepositoryInterface
{
    public function create(
        ScenarioId $scenarioId,
        ScenarioStepExternalId $externalId,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $processedAt,
        ?string $name,
        array $data,
        ?ScenarioStepExternalId $replyToExternalId,
        ?ScenarioStepId $replyToId,
    ): ScenarioStep;
}