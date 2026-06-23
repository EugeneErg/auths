<?php

declare(strict_types=1);

namespace EugeneErg\Auths\Contracts\Repositories\Write;

use DateTimeImmutable;
use EugeneErg\Auths\Entities\ScenarioStep;
use EugeneErg\Auths\ValueObjects\ScenarioId;
use EugeneErg\Auths\ValueObjects\ScenarioStepExternalId;
use EugeneErg\Auths\ValueObjects\ScenarioStepId;

interface WriteScenarioStepRepositoryInterface
{
    public function create(
        ScenarioId $scenarioId,
        ScenarioStepExternalId $externalId,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $processedAt,
        string|null $name,
        array $data,
        ScenarioStepExternalId|null $replyToExternalId,
        ScenarioStepId|null $replyToId,
    ): ScenarioStep;
}
